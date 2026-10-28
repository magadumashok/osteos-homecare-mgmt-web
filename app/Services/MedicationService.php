<?php

namespace App\Services;

use App\Models\AdministerMedication;
use App\Models\MedicationSchedule;
use App\Models\PatientGuardian;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MedicationService
{
    public function __construct(
        private readonly UserDirectoryService $users,
        private readonly FileStorageService $files,
    ) {}

    public function addSchedule(array $data, User $enteredBy): MedicationSchedule
    {
        return MedicationSchedule::create([
            ...$data,
            'entered_by' => $data['entered_by'] ?? $enteredBy->username,
        ]);
    }

    /** A patient's medication schedule with the name of who entered each line. */
    public function scheduleFor(string $patientId): Collection
    {
        $medicines = MedicationSchedule::where('patient_id', $patientId)->orderBy('date', 'desc')->get();
        $staff = $this->users->byUsername($medicines->pluck('entered_by'));

        $medicines->each(function (MedicationSchedule $medicine) use ($staff) {
            if ($name = $staff->get($medicine->entered_by)?->displayName()) {
                $medicine->nurse_name = $name;
            }
        });

        return $this->withDeleteInfo($medicines, $patientId);
    }

    /**
     * The day's medicines for a nurse to tick off: each scheduled medicine
     * with whichever sessions were already given.
     */
    public function checklist(string $patientId, string $date): Collection
    {
        $medicines = MedicationSchedule::where('patient_id', $patientId)->where('date', $date)->get();

        $given = AdministerMedication::where('patient_id', $patientId)
            ->where('date', $date)
            ->get()
            ->keyBy('medicine_name');

        $medicines->each(function (MedicationSchedule $medicine) use ($given) {
            foreach (AdministerMedication::SESSIONS as $session) {
                if ($entry = $given->get($medicine->medicine_name)?->{$session}) {
                    $medicine->{$session} = $entry;
                }
            }
        });

        return $this->withDeleteInfo($medicines, $patientId);
    }

    /** What was given on a date, with each session's guardian name and signature. */
    public function administeredOn(string $patientId, string $date): Collection
    {
        $records = AdministerMedication::where('patient_id', $patientId)->where('date', $date)->get();
        $guardians = PatientGuardian::where('patient_id', $patientId)->get()->keyBy('guardian_id');

        return $records->each(function (AdministerMedication $record) use ($guardians) {
            foreach (AdministerMedication::SESSIONS as $session) {
                $entry = $record->{$session};
                $guardian = is_array($entry) ? $guardians->get($entry['guardian_id'] ?? null) : null;

                if ($guardian) {
                    $record->{$session} = [
                        ...$entry,
                        'guardian_name' => $guardian->guardian_name,
                        'guardian_signature' => $guardian->guardian_signature,
                    ];
                }
            }
        });
    }

    /**
     * Record a medicine given in one session. The witnessing guardian is
     * either an existing one (`guardian_id`) or a new one (`guardian_name`
     * plus a base64 PNG `image` of their signature).
     */
    public function recordAdministration(array $data, User $nurse): AdministerMedication
    {
        $session = $data['medicine_type'] ?? null;
        if (! in_array($session, AdministerMedication::SESSIONS, true)) {
            throw ValidationException::withMessages(['medicine_type' => 'Choose morning, afternoon, evening or night.']);
        }

        $guardianId = $this->guardianFor($data);

        $record = AdministerMedication::firstOrNew([
            'patient_id' => $data['patient_id'],
            'medicine_name' => $data['medicine_name'],
            'date' => $data['date'],
        ]);

        // The legacy app wrote repeat sessions to a field literally named
        // "medicine_type"; each session now goes to its own field.
        $record->{$session} = [
            'nurse_id' => $data['nurse_id'] ?? $nurse->username,
            'nurse_name' => $data['nurse_name'] ?? $nurse->displayName(),
            'given_date' => $data['medicine_date'] ?? null,
            'given_time' => $data['medicine_time'] ?? null,
            'guardian_id' => $guardianId,
        ];
        $record->save();

        return $record;
    }

    /**
     * Remove a scheduled medicine that was entered wrongly or twice. Doses
     * are recorded per medicine name and date, so a duplicate's doses stay
     * with the copy that remains; deleting the only entry removes that
     * day's given doses with it (they were given against a wrong entry).
     */
    public function deleteSchedule(string $patientId, string $id): void
    {
        $medicine = MedicationSchedule::where('patient_id', $patientId)->where('_id', $id)->firstOrFail();

        $copies = MedicationSchedule::where('patient_id', $patientId)
            ->where('medicine_name', $medicine->medicine_name)
            ->where('date', $medicine->date)
            ->count();

        if ($copies <= 1) {
            AdministerMedication::where('patient_id', $patientId)
                ->where('medicine_name', $medicine->medicine_name)
                ->where('date', $medicine->date)
                ->delete();
        }

        $medicine->delete();
    }

    /**
     * Flag each medicine for the app's delete confirmation: `duplicate`
     * (another entry with the same name and date) and `doses_given` (a
     * session was given that day).
     */
    private function withDeleteInfo(Collection $medicines, string $patientId): Collection
    {
        $key = fn ($m) => $m->medicine_name.'|'.$m->date;

        $given = AdministerMedication::where('patient_id', $patientId)
            ->whereIn('date', $medicines->pluck('date')->unique()->values()->all())
            ->get()
            ->filter(fn ($g) => collect(AdministerMedication::SESSIONS)->contains(fn ($s) => ! empty($g->{$s})))
            ->map($key)
            ->flip();
        $copies = $medicines->countBy($key);

        return $medicines->each(function (MedicationSchedule $m) use ($key, $given, $copies) {
            $m->duplicate = $copies->get($key($m), 0) > 1;
            $m->doses_given = $given->has($key($m));
        });
    }

    public function guardians(string $patientId): Collection
    {
        return PatientGuardian::where('patient_id', $patientId)->get();
    }

    private function guardianFor(array $data): string
    {
        if (empty($data['guardian_name'])) {
            return (string) ($data['guardian_id'] ?? '');
        }

        $handle = preg_replace('/\s+/', '', strtolower($data['guardian_name']));
        $guardianId = $handle.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        if (! empty($data['image'])) {
            $signature = $this->files->storeBase64(
                $data['image'],
                "patients/{$data['patient_id']}/guardiansignature/{$guardianId}",
                Str::slug($handle).'_signature.png',
            );

            PatientGuardian::create([
                'patient_id' => $data['patient_id'],
                'guardian_id' => $guardianId,
                'guardian_name' => $data['guardian_name'],
                'guardian_signature' => $signature,
            ]);
        }

        return $guardianId;
    }
}
