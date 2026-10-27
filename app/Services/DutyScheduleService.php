<?php

namespace App\Services;

use App\Exceptions\ScheduleConflictException;
use App\Models\DriverSchedule;
use App\Models\Patient;
use App\Models\StaffDutySchedule;
use App\Models\User;
use App\Support\TimeFormat;
use Illuminate\Support\Collection;

/**
 * Duties of nurses, physiotherapists, doctors and caretakers with patients:
 * creating, rescheduling, attendance and the lists built from them.
 */
class DutyScheduleService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly PatientService $patients,
        private readonly UserDirectoryService $users,
        private readonly DriverScheduleService $drivers,
        private readonly ScheduleConflictService $conflicts,
        private readonly ReferenceDataService $reference,
        private readonly WhatsAppService $whatsapp,
        private readonly PushNotificationService $push,
    ) {}

    public function findOrFail(string $id): StaffDutySchedule
    {
        return StaffDutySchedule::where('_id', $id)->firstOrFail();
    }

    /**
     * Schedule a duty, book the driver (when one is named) and notify the
     * staff member, patient and driver on WhatsApp (staff and driver also
     * get a push notification).
     *
     * @return array{duty: StaffDutySchedule, driver_schedule: ?DriverSchedule, driver_duplicate: bool}
     *
     * @throws ScheduleConflictException when $checkConflicts and the slot is taken
     */
    public function create(array $data, bool $checkConflicts = true): array
    {
        $data = $this->withLiveIn($data);
        $patient = $this->patients->findOrFail($data['patient_id'] ?? null);

        if ($checkConflicts && ($conflict = $this->conflicts->find($data))) {
            throw new ScheduleConflictException($conflict);
        }

        $driver = $this->users->driverNamed($data['driver'] ?? null);

        $duty = StaffDutySchedule::create([
            ...array_intersect_key($data, array_flip(StaffDutySchedule::SCHEDULE_FIELDS)),
            ...array_intersect_key($data, array_flip(['live_in', 'covers_duty_id'])),
            ...$this->packageOf($patient),
            ...StaffDutySchedule::freshAttendance(),
        ]);

        $driverSchedule = null;
        if ($driver) {
            $driverSchedule = $this->drivers->create([
                'driver_id' => $driver->username,
                'staff_id' => $duty->staff_id,
                'address' => $patient->address,
                ...$duty->only(['date', 'end_date', 'start_time', 'end_time']),
            ]);
        }

        $this->notifyAssignment($duty, $patient, $driver, rescheduled: false);

        return [
            'duty' => $duty,
            'driver_schedule' => $driverSchedule,
            'driver_duplicate' => $driver !== null && $driverSchedule === null,
        ];
    }

    /**
     * The same duty on several date ranges. Every range is checked for the
     * staff member being double-booked before any is saved (the legacy
     * endpoint saved ranges until the first clash, then stopped).
     *
     * @param  list<array{start_date: string, end_date: string}>  $ranges
     * @return Collection<int, StaffDutySchedule>
     *
     * @throws ScheduleConflictException
     */
    public function createSeries(array $data, array $ranges): Collection
    {
        $duties = collect($ranges)->map(fn (array $range) => [
            ...array_intersect_key($data, array_flip(StaffDutySchedule::SCHEDULE_FIELDS)),
            'date' => $range['start_date'],
            'end_date' => $range['end_date'],
        ]);

        foreach ($duties as $i => $duty) {
            $overlapsSeries = $this->conflicts->overlapsAny($duty, $duties->except($i)->all());

            if ($overlapsSeries || $this->conflicts->find($duty, checkPatient: false)) {
                throw new ScheduleConflictException(ScheduleConflictService::STAFF);
            }
        }

        $created = $duties->map(fn (array $duty) => StaffDutySchedule::create([
            ...$duty,
            ...StaffDutySchedule::freshAttendance(),
        ]));

        $this->pushSeries($created);

        return $created;
    }

    /**
     * Move a duty to a new time or staff member. Attendance marks are
     * reset and everyone involved is told on WhatsApp.
     *
     * @throws ScheduleConflictException
     */
    public function reschedule(StaffDutySchedule $duty, array $data): StaffDutySchedule
    {
        $changes = array_intersect_key($data, array_flip(StaffDutySchedule::SCHEDULE_FIELDS));
        $changes = $this->withLiveIn([...$changes, 'role' => $changes['role'] ?? $duty->role]);
        $next = [...$duty->only(StaffDutySchedule::SCHEDULE_FIELDS), ...$changes];

        if ($conflict = $this->conflicts->find($next, ignoreId: (string) $duty->getKey())) {
            throw new ScheduleConflictException($conflict);
        }

        $duty->update([...$changes, 'not_attended' => false, 'not_attended_reason' => '']);

        $patient = $this->patients->find($duty->patient_id);
        if ($patient) {
            $this->notifyAssignment($duty, $patient, $this->users->driverNamed($duty->driver), rescheduled: true);
        }

        return $duty;
    }

    public function signIn(StaffDutySchedule $duty, array $data): void
    {
        $duty->update([
            'sign_in' => true,
            'signin_time' => $data['signin_time'] ?? null,
            'signin_latitude' => $data['signin_latitude'] ?? null,
            'signin_longitude' => $data['signin_longitude'] ?? null,
        ]);
    }

    public function signOut(StaffDutySchedule $duty, array $data): void
    {
        $duty->update([
            'sign_out' => true,
            'signout_time' => $data['signout_time'] ?? null,
            'signout_latitude' => $data['signout_latitude'] ?? null,
            'signout_longitude' => $data['signout_longitude'] ?? null,
        ]);
    }

    public function markNotAttended(StaffDutySchedule $duty, ?string $reason): void
    {
        $duty->update(['not_attended' => true, 'not_attended_reason' => (string) $reason]);
    }

    /**
     * A staff member's work on a date, with patient name and address:
     * visits ending that day, plus 24-hour duties running through it
     * (shown every day until their end date, except days they skip).
     */
    public function forStaffOn(string $staffId, string $date): Collection
    {
        $visits = StaffDutySchedule::where('staff_id', $staffId)
            ->where('end_date', $date)
            ->where('not_attended', false)
            ->where('live_in', '!=', true)
            ->get();

        $liveIn = $this->liveInOn($date)
            ->where('staff_id', $staffId)
            ->get()
            ->reject(fn (StaffDutySchedule $duty) => $duty->isSkipped($date));

        $duties = $liveIn->concat($visits)->sortBy('start_time')->values();

        return $this->withPatients($duties, withAddress: true);
    }

    /** Every duty starting on a date, with staff and patient names (for booking drivers). */
    public function startingOn(string $date): Collection
    {
        $duties = StaffDutySchedule::where('date', $date)->orderBy('start_time')->get();

        return $this->withStaff($this->withPatients($duties, withAddress: true), 'staff_name');
    }

    /** The duty board for a day: duties starting then, plus 24-hour duties already running. */
    public function onDay(string $date): Collection
    {
        $running = $this->liveInOn($date)->where('date', '<', $date)->get();
        $duties = $running->concat(StaffDutySchedule::where('date', $date)->orderBy('start_time')->get());

        return $this->decorate($duties);
    }

    /** Live-in duties running on a date (not cancelled). */
    public function liveInOn(string $date)
    {
        return StaffDutySchedule::where('live_in', true)
            ->where('date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('not_attended', false);
    }

    /** Duties starting on or after a date, newest first, with staff (`name`) and patient names. */
    public function since(string $date): Collection
    {
        $duties = StaffDutySchedule::where('date', '>=', $date)->orderBy('date', 'desc')->get();

        return $this->withStaff($this->withPatients($duties), 'name');
    }

    /** A patient's duties, newest first, with the staff member's name. */
    public function forPatient(string $patientId): Collection
    {
        $duties = StaffDutySchedule::where('patient_id', $patientId)->orderBy('date', 'desc')->get();

        return $this->withStaff($duties, 'staff_name');
    }

    /** Attended (or upcoming) sessions of a patient with one role. */
    public function sessions(string $patientId, ?string $role): Collection
    {
        return StaffDutySchedule::where('patient_id', $patientId)
            ->when($role, fn ($q) => $q->where('role', $role))
            ->where('not_attended', false)
            ->orderBy('date')
            ->get();
    }

    /** Every duty of a staff member, newest first, with patient names. */
    public function history(string $staffId): Collection
    {
        $duties = StaffDutySchedule::where('staff_id', $staffId)->orderBy('date', 'desc')->get();

        return $this->withPatients($duties);
    }

    /** @return array{staff_name: ?string, patient_name: ?string, attendance_data: Collection} */
    public function attendance(string $staffId, string $patientId): array
    {
        return [
            'staff_name' => $this->users->find($staffId)?->displayName(),
            'patient_name' => $this->patients->find($patientId)?->patient_name,
            'attendance_data' => StaffDutySchedule::where('staff_id', $staffId)
                ->where('patient_id', $patientId)
                ->orderBy('date', 'desc')
                ->get(),
        ];
    }

    /** The duty matching an exact slot (how the legacy app found a duty it listed). */
    public function findSlot(array $slot): ?StaffDutySchedule
    {
        $fields = ['patient_id', 'staff_id', 'date', 'start_time', 'end_date', 'end_time'];

        return StaffDutySchedule::where(array_intersect_key($slot, array_flip($fields)))->first();
    }

    /** Add patient name and address, and staff name, to duties. */
    public function decorate(Collection $duties): Collection
    {
        return $this->withStaff($this->withPatients($duties, withAddress: true), 'staff_name');
    }

    /** Caretaker duties get whole-day times; other roles keep the times sent. */
    private function withLiveIn(array $data): array
    {
        return StaffDutySchedule::isLiveInRole($data['role'] ?? null)
            ? [...$data, ...StaffDutySchedule::liveInFields()]
            : [...$data, 'live_in' => false];
    }

    /**
     * Tell the staff member, patient and driver about a new or moved duty.
     * Which template is used depends on whether the patient is on a
     * service package (the package and fee are then included).
     */
    private function notifyAssignment(StaffDutySchedule $duty, Patient $patient, ?User $driver, bool $rescheduled): void
    {
        $staff = $this->users->find($duty->staff_id);
        $staffName = $staff?->displayName();
        $patientMobile = $patient->mobile ?: 'not provided';
        // Live-in duties have no visit time: the templates' time slot says how long the stay is.
        $time = $duty->live_in ? "24-hour care until {$duty->end_date}" : TimeFormat::to12Hour($duty->start_time);
        $homecare = $this->tenants->displayName();
        $driverName = trim((string) ($driver?->name ?? $duty->driver));
        $hasDriver = $driverName !== '';

        // The no-driver templates have no driver placeholder.
        $toStaff = [$staffName, $patient->patient_name, $duty->date, $time, $patient->address, $patient->address_link, $patientMobile];
        if ($hasDriver) {
            $toStaff[] = $driverName;
        }
        $toPatient = [$patient->patient_name, $staffName, $homecare, $duty->date, $time];

        if ($patient->service_package) {
            $package = [$patient->service_package, trim($patient->fees.' '.config('homecare.twilio.currency'))];
            $this->whatsapp->send($this->reference->userNumber($staff), $hasDriver ? 'staff_duty_with_package' : 'staff_duty_with_package_no_driver', [...$toStaff, ...$package]);
            $this->whatsapp->send($patient->mobile, $rescheduled ? 'patient_duty_rescheduled' : 'patient_duty_with_package', $rescheduled ? $toPatient : [...$toPatient, ...$package]);
        } else {
            $this->whatsapp->send($this->reference->userNumber($staff), $hasDriver ? 'staff_duty' : 'staff_duty_no_driver', $toStaff);
            $this->whatsapp->send($patient->mobile, $rescheduled ? 'patient_duty_rescheduled' : 'patient_duty', $toPatient);
        }

        if ($driver) {
            $this->whatsapp->send($this->reference->userNumber($driver), $rescheduled ? 'driver_duty_rescheduled' : 'driver_duty', [
                $driver->name, $staffName, $duty->date, $time, $patient->address, $patient->address_link, $patientMobile,
            ]);
        }

        $type = $rescheduled ? 'duty_rescheduled' : 'duty_assigned';
        $when = match (true) {
            ! $duty->live_in => "on {$duty->date} at {$time}",
            $duty->date === $duty->end_date => "on {$duty->date} (24-hour care)",
            default => "from {$duty->date} to {$duty->end_date} (24-hour care)",
        };
        $data = ['type' => $type, 'dutyId' => (string) $duty->getKey(), 'date' => $duty->date];

        $this->push->notify(
            $staff,
            $rescheduled ? 'Duty rescheduled' : 'New duty',
            "{$patient->patient_name} {$when}.",
            $data,
        );

        if ($driver) {
            $this->push->notify(
                $driver,
                $rescheduled ? 'Trip rescheduled' : 'New trip',
                "Drive {$staffName} to {$patient->patient_name} {$when}.",
                [...$data, 'type' => $rescheduled ? 'trip_rescheduled' : 'trip_assigned'],
            );
        }
    }

    /** One push for a whole series, not one per duty. */
    private function pushSeries(Collection $duties): void
    {
        $first = $duties->first();
        if (! $first) {
            return;
        }

        $patient = $this->patients->find($first->patient_id);
        $count = $duties->count();
        $range = $count > 1 ? "from {$first->date} to {$duties->last()->date}" : "on {$first->date}";

        $this->push->notify(
            $this->users->find($first->staff_id),
            $count > 1 ? "{$count} new duties" : 'New duty',
            trim(($patient?->patient_name ?? '').' '.$range.' at '.TimeFormat::to12Hour($first->start_time)).'.',
            ['type' => 'duty_assigned', 'date' => $first->date],
        );
    }

    private function packageOf(Patient $patient): array
    {
        if (! $patient->service_package) {
            return [];
        }

        return [
            'service_package' => $patient->service_package,
            'fees' => $patient->fees,
            'services' => [['service_package' => $patient->service_package, 'fees' => $patient->fees]],
        ];
    }

    private function withPatients(Collection $duties, bool $withAddress = false): Collection
    {
        $patients = $this->patients->byUsername($duties->pluck('patient_id'), ['username', 'patient_name', 'address']);

        return $duties->each(function (StaffDutySchedule $duty) use ($patients, $withAddress) {
            $duty->patient_name = $patients->get($duty->patient_id)?->patient_name;
            if ($withAddress) {
                $duty->address = $patients->get($duty->patient_id)?->address;
            }
        });
    }

    private function withStaff(Collection $duties, string $as): Collection
    {
        $staff = $this->users->byUsername($duties->pluck('staff_id'));

        return $duties->each(fn (StaffDutySchedule $duty) => $duty->{$as} = $staff->get($duty->staff_id)?->displayName());
    }
}
