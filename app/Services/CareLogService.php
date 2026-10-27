<?php

namespace App\Services;

use App\Exceptions\ScheduleConflictException;
use App\Models\CareLog;
use App\Models\StaffDutySchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Day-to-day log of caretakers' 24-hour duties (the `care_taker_services`
 * collection): what was done each day, symptoms reported, days the
 * caretaker skipped and who covered them.
 */
class CareLogService
{
    public function __construct(
        private readonly DutyScheduleService $duties,
        private readonly ScheduleConflictService $conflicts,
        private readonly PatientService $patients,
        private readonly UserDirectoryService $users,
        private readonly PushNotificationService $push,
    ) {}

    /** Every logged or skipped day of a duty, newest first. */
    public function forDuty(StaffDutySchedule $duty): Collection
    {
        return CareLog::where('duty_id', (string) $duty->getKey())->orderBy('date', 'desc')->get();
    }

    /**
     * The admin's view of a day: every 24-hour duty running that day (with
     * patient and caretaker names) and its `log` for the day, or null when
     * the caretaker has not sent an update yet.
     */
    public function day(string $date, ?string $patientId = null): Collection
    {
        $duties = $this->duties->liveInOn($date)
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->get();

        $logs = CareLog::where('date', $date)
            ->whereIn('duty_id', $duties->map(fn ($d) => (string) $d->getKey())->all())
            ->get()
            ->keyBy('duty_id');

        return $this->duties->decorate($duties)
            ->each(fn (StaffDutySchedule $duty) => $duty->log = $logs->get((string) $duty->getKey()))
            ->sortBy('patient_name')
            ->values();
    }

    /**
     * Save the caretaker's update for a day (one per duty and day; saving
     * again replaces it). Symptoms are pushed to the admins straight away.
     *
     * @param  array{tasks?: list<array{name: string, done?: bool, time?: ?string, note?: ?string}>, symptoms?: ?string, notes?: ?string}  $data
     */
    public function save(StaffDutySchedule $duty, string $date, array $data, User $by): CareLog
    {
        $this->assertDay($duty, $date);

        if ($date > now()->format('Y-m-d')) {
            throw ValidationException::withMessages(['date' => 'You can only update today or earlier days.']);
        }
        if ($duty->isSkipped($date)) {
            throw ValidationException::withMessages(['date' => 'This day was skipped.']);
        }

        $symptoms = trim((string) ($data['symptoms'] ?? ''));

        $log = CareLog::updateOrCreate(
            ['duty_id' => (string) $duty->getKey(), 'date' => $date],
            [
                'patient_id' => $duty->patient_id,
                'staff_id' => $duty->staff_id,
                'staff_name' => $this->users->find($duty->staff_id)?->displayName(),
                'status' => CareLog::LOGGED,
                'tasks' => array_values(array_map(fn (array $task) => [
                    'name' => $task['name'],
                    'done' => (bool) ($task['done'] ?? false),
                    'time' => $task['time'] ?? null,
                    'note' => $task['note'] ?? null,
                ], $data['tasks'] ?? [])),
                'symptoms' => $symptoms,
                'notes' => trim((string) ($data['notes'] ?? '')),
                'updated_time' => now()->format('H:i'),
            ],
        );

        if ($symptoms !== '') {
            $patient = $this->patients->find($duty->patient_id);
            $this->notifyAdmins(
                'Symptoms reported',
                ($patient?->patient_name ?? $duty->patient_id).": {$symptoms}",
                ['type' => 'care_symptoms', 'dutyId' => (string) $duty->getKey(), 'date' => $date],
                except: $by,
            );
        }

        return $log;
    }

    /**
     * The caretaker will not be there on a day. The day drops off their
     * schedule, and the admins are told so they can assign a cover.
     */
    public function skip(StaffDutySchedule $duty, string $date, string $reason, User $by): CareLog
    {
        $this->assertDay($duty, $date);

        if ($duty->isSkipped($date)) {
            throw ValidationException::withMessages(['date' => 'This day is already skipped.']);
        }

        $key = ['duty_id' => (string) $duty->getKey(), 'date' => $date];
        if (CareLog::where($key)->where('status', CareLog::LOGGED)->exists()) {
            throw ValidationException::withMessages(['date' => 'This day already has a care update.']);
        }

        $duty->update(['skipped_dates' => array_values(array_unique([...(array) ($duty->skipped_dates ?? []), $date]))]);

        $staffName = $this->users->find($duty->staff_id)?->displayName();
        $log = CareLog::updateOrCreate($key, [
            'patient_id' => $duty->patient_id,
            'staff_id' => $duty->staff_id,
            'staff_name' => $staffName,
            'status' => CareLog::SKIPPED,
            'skip_reason' => $reason,
            'covered_by' => null,
            'cover_duty_id' => null,
            'updated_time' => now()->format('H:i'),
        ]);

        $patient = $this->patients->find($duty->patient_id);
        $this->notifyAdmins(
            'Caretaker cover needed',
            "{$staffName} can't be with ".($patient?->patient_name ?? $duty->patient_id)." on {$date}: {$reason}",
            ['type' => 'care_day_skipped', 'dutyId' => (string) $duty->getKey(), 'date' => $date],
            except: $by,
        );

        return $log;
    }

    /**
     * Give a skipped day to another caretaker: a one-day 24-hour duty for
     * them (they are notified like any new duty). The patient already
     * "has" a caretaker that day on paper, so only the cover's own
     * schedule is checked for clashes.
     *
     * @throws ScheduleConflictException
     */
    public function cover(StaffDutySchedule $duty, string $date, string $staffId): StaffDutySchedule
    {
        $log = CareLog::where('duty_id', (string) $duty->getKey())->where('date', $date)->first();

        if (! $duty->isSkipped($date) || ! $log) {
            throw ValidationException::withMessages(['date' => 'The caretaker has not skipped this day.']);
        }
        if ($log->cover_duty_id) {
            throw ValidationException::withMessages(['date' => 'This day already has a cover caretaker.']);
        }
        if ($staffId === $duty->staff_id) {
            throw ValidationException::withMessages(['staff_id' => 'Choose a different caretaker.']);
        }

        $data = [
            'staff_id' => $staffId,
            'role' => $duty->role,
            'patient_id' => $duty->patient_id,
            'date' => $date,
            'end_date' => $date,
            'care_tasks' => $duty->care_tasks ?? [],
            'covers_duty_id' => (string) $duty->getKey(),
            ...StaffDutySchedule::liveInFields(),
        ];

        if ($this->conflicts->find($data, checkPatient: false)) {
            throw new ScheduleConflictException(ScheduleConflictService::STAFF);
        }

        $cover = $this->duties->create($data, checkConflicts: false)['duty'];

        $log->update(['covered_by' => $staffId, 'cover_duty_id' => (string) $cover->getKey()]);

        return $cover;
    }

    private function assertDay(StaffDutySchedule $duty, string $date): void
    {
        if (! $duty->live_in) {
            throw ValidationException::withMessages(['duty' => 'Only 24-hour caretaker duties have a daily care log.']);
        }
        if (! $duty->covers($date)) {
            throw ValidationException::withMessages(['date' => "This duty runs from {$duty->date} to {$duty->end_date}."]);
        }
        if ($duty->not_attended) {
            throw ValidationException::withMessages(['duty' => 'This duty was cancelled.']);
        }
    }

    /** Push to the center's admins (and owner), except whoever caused it. */
    private function notifyAdmins(string $title, string $body, array $data, ?User $except = null): void
    {
        $this->users->withRole('admin')
            ->push($this->users->owner())
            ->filter()
            ->unique('username')
            ->reject(fn (User $admin) => $except && $admin->username === $except->username)
            ->each(fn (User $admin) => $this->push->notify($admin, $title, $body, $data));
    }
}
