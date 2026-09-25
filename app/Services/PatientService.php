<?php

namespace App\Services;

use App\Models\DischargedPatient;
use App\Models\Patient;
use App\Models\StaffDutySchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PatientService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly SequenceService $sequences,
        private readonly ReferenceDataService $reference,
        private readonly UserDirectoryService $users,
        private readonly FileStorageService $files,
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function all(): Collection
    {
        return Patient::orderBy('username')->get();
    }

    public function admitted(): Collection
    {
        return Patient::admitted()->orderBy('username')->get();
    }

    public function find(?string $username): ?Patient
    {
        return $username ? Patient::where('username', $username)->first() : null;
    }

    public function findOrFail(?string $username): Patient
    {
        return $this->find($username) ?? throw (new ModelNotFoundException)->setModel(Patient::class, [$username]);
    }

    /**
     * Patients by username in one query, for decorating lists.
     *
     * @return Collection<string, Patient>
     */
    public function byUsername(iterable $usernames, array $columns = ['*']): Collection
    {
        $usernames = collect($usernames)->filter()->unique()->values();

        if ($usernames->isEmpty()) {
            return collect();
        }

        return Patient::whereIn('username', $usernames->all())->get($columns)->keyBy('username');
    }

    /**
     * Register a patient directly (without the lead → assessment flow).
     * `mobile` is local; the dialling code of `country` is prepended.
     */
    public function register(array $data): Patient
    {
        $patient = Patient::create([
            ...$this->withServices($data),
            'username' => $this->nextUsername(),
            'mobile' => $this->reference->internationalNumber($data['country'] ?? null, $data['mobile'] ?? null),
            'address_link' => $this->mapsLink($data),
            'terms_conditions' => false,
            'consent_form' => false,
            'amount_paid' => false,
            'discharged' => false,
        ]);

        $this->whatsapp->send($patient->mobile, 'patient_registered', [
            $patient->patient_name,
            $this->tenants->displayName(),
        ]);

        return $patient;
    }

    /**
     * A staff member registers a patient they are seeing now, and the visit
     * is logged as their own duty.
     *
     * @return array{0: Patient, 1: StaffDutySchedule}
     */
    public function registerWalkIn(User $staff, array $patientData, array $duty): array
    {
        // The legacy app prefixed every walk-in number with +91; use the
        // patient's (or else the center's) country instead.
        $country = $patientData['country'] ?? $this->tenants->homecare()?->country;

        $patient = Patient::create([
            ...$this->withServices($patientData),
            'username' => $this->nextUsername(),
            'mobile' => $this->reference->internationalNumber($country, $patientData['mobile'] ?? null),
            'terms_conditions' => false,
            'consent_form' => false,
            'amount_paid' => false,
            'discharged' => false,
        ]);

        $schedule = StaffDutySchedule::create([
            ...$duty,
            ...StaffDutySchedule::freshAttendance(),
            'staff_id' => $staff->username,
            'patient_id' => $patient->username,
            ...($patient->service_package ? $patient->only(['service_package', 'fees', 'services']) : []),
        ]);

        return [$patient, $schedule];
    }

    /** Only the editable fields are applied (the legacy endpoint wrote every request key). */
    public function update(Patient $patient, array $data): Patient
    {
        $patient->update(array_intersect_key($data, array_flip(Patient::EDITABLE_FIELDS)));

        return $patient;
    }

    /** Services delivered, set by the staff member on the duty and copied to the patient. */
    public function updateServices(Patient $patient, ?StaffDutySchedule $duty, array $services): void
    {
        $duty?->update(['services' => $services]);
        $patient->update(['services' => $services]);
    }

    /**
     * Terms, consent and payment flags, plus the patient's signature (base64 PNG).
     *
     * @return string the stored signature's file name, or '' without one
     */
    public function recordConsent(Patient $patient, array $flags, ?string $signature): string
    {
        $update = array_intersect_key($flags, array_flip(['terms_conditions', 'consent_form', 'amount_paid']));
        $fileName = '';

        if ($signature) {
            $fileName = 'signature_'.Str::random(10).'.png';
            $update['digitalsignature'] = $this->files->storeBase64(
                $signature, "assessments/{$patient->username}/digitalsignature", $fileName,
            );
        }

        $patient->update($update);

        return $fileName;
    }

    /** The planned discharge date, set by an admin before the nurse discharges. */
    public function scheduleDischarge(Patient $patient, string $date, ?string $time): void
    {
        $patient->update(['discharge' => ['date' => $date, 'time' => $time]]);
    }

    public function discharge(Patient $patient, User $by, array $summary): DischargedPatient
    {
        $record = DischargedPatient::create([
            'patient_id' => $patient->username,
            'date' => $summary['date'] ?? now()->format('Y-m-d'),
            'time' => $summary['time'] ?? now()->format('H:i'),
            'summary' => $summary['summary'] ?? null,
            'discharged_by' => $by->username,
        ]);

        $patient->update(['discharged' => true]);

        return $record;
    }

    /** Patients this staff member has had duties with, whose discharge date is set. */
    public function awaitingDischargeFor(User $staff): Collection
    {
        return Patient::whereIn('username', $this->patientIdsOfStaff($staff->username))
            ->admitted()
            ->whereNotNull('discharge')
            ->get();
    }

    /** Patients (username and name) this staff member has had duties with. */
    public function patientsOfStaff(string $staffId): Collection
    {
        return Patient::whereIn('username', $this->patientIdsOfStaff($staffId))->get(['username', 'patient_name']);
    }

    /** @return list<string> */
    private function patientIdsOfStaff(string $staffId): array
    {
        $ids = StaffDutySchedule::raw(fn ($collection) => $collection->distinct('patient_id', ['staff_id' => $staffId]));

        return array_values(array_filter((array) $ids, 'is_string'));
    }

    /**
     * Discharged patients: the patient record with discharge details and
     * the discharging staff member's name.
     */
    public function discharged(): Collection
    {
        $records = DischargedPatient::orderBy('date', 'desc')->get();
        $patients = $this->byUsername($records->pluck('patient_id'));
        $staff = $this->users->byUsername($records->pluck('discharged_by'));

        return $records
            ->filter(fn ($record) => $patients->has($record->patient_id))
            ->map(function (DischargedPatient $record) use ($patients, $staff) {
                return [
                    ...$patients[$record->patient_id]->toArray(),
                    'discharged_by' => $staff->get($record->discharged_by)?->name,
                    'discharged_date' => $record->date,
                    'discharged_time' => $record->time,
                    'discharged_summary' => $record->summary,
                ];
            })
            ->values();
    }

    /**
     * Next patient username, "<first 3 of homecare_id>p<nnn>". The legacy
     * app used count()+1, which repeated numbers after concurrent or
     * deleted registrations; the counter starts from the current count.
     */
    public function nextUsername(): string
    {
        $prefix = substr((string) $this->tenants->current(), 0, 3).'p';

        do {
            $number = $this->sequences->next('patient', fn () => Patient::count());
            $username = $prefix.sprintf('%03d', $number);
        } while (Patient::where('username', $username)->exists());

        return $username;
    }

    /** A patient on a service package starts with it as their first service line. */
    private function withServices(array $data): array
    {
        if (! empty($data['service_package']) && ! isset($data['services'])) {
            $data['services'] = [['service_package' => $data['service_package'], 'fees' => $data['fees'] ?? null]];
        }

        return $data;
    }

    private function mapsLink(array $data): ?string
    {
        $destination = implode(' ', array_filter([$data['address'] ?? null, $data['state'] ?? null, $data['country'] ?? null]));

        return $destination === '' ? null : 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($destination);
    }
}
