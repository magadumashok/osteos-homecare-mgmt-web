<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\StaffDutySchedule;
use App\Support\TimeFormat;
use Carbon\CarbonImmutable;

/**
 * Detects double-booked duties: a patient with two staff of the same role
 * at once, or one staff member in two places at once.
 *
 * A duty occupies [date start_time, end_date end_time + buffer_time]. Two
 * duties conflict when those intervals overlap. Duties can run overnight,
 * so candidates starting the day before are checked too.
 */
class ScheduleConflictService
{
    public const PATIENT = 'patient';

    public const STAFF = 'staff';

    private const FIELDS = ['_id', 'date', 'end_date', 'start_time', 'end_time', 'buffer_time'];

    /**
     * @param  array{date: string, end_date?: ?string, start_time: string, end_time: string,
     *               staff_id?: ?string, patient_id?: ?string, role?: ?string}  $duty
     * @return self::PATIENT|self::STAFF|null
     */
    public function find(array $duty, ?string $ignoreId = null, bool $checkPatient = true, bool $checkStaff = true): ?string
    {
        if ($checkPatient && ! empty($duty['patient_id'])) {
            $sameSlot = $this->candidates($duty, $ignoreId)
                ->where('patient_id', $duty['patient_id'])
                ->when(! empty($duty['role']), fn ($q) => $q->where('role', $duty['role']))
                ->get(self::FIELDS);

            if ($this->overlapsAny($duty, $sameSlot)) {
                return self::PATIENT;
            }
        }

        if ($checkStaff && ! empty($duty['staff_id'])) {
            $sameStaff = $this->candidates($duty, $ignoreId)
                ->where('staff_id', $duty['staff_id'])
                ->get(self::FIELDS);

            if ($this->overlapsAny($duty, $sameStaff)) {
                return self::STAFF;
            }
        }

        return null;
    }

    /** @param  iterable<array|StaffDutySchedule>  $existing */
    public function overlapsAny(array $duty, iterable $existing): bool
    {
        foreach ($existing as $other) {
            if ($this->overlaps($other instanceof StaffDutySchedule ? $other->toArray() : $other, $duty)) {
                return true;
            }
        }

        return false;
    }

    /** Does the requested duty start before the existing one (plus its buffer) ends, and vice versa? */
    public function overlaps(array $existing, array $requested): bool
    {
        [$existingStart, $existingEnd] = $this->interval($existing);
        [$requestedStart, $requestedEnd] = $this->interval($requested);

        $existingEnd = $existingEnd->addMinutes(TimeFormat::minutes($existing['buffer_time'] ?? null));

        return $existingStart < $requestedEnd && $requestedStart < $existingEnd;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function interval(array $duty): array
    {
        $start = TimeFormat::at($duty['date'], $duty['start_time'] ?? null);
        $end = TimeFormat::at(($duty['end_date'] ?? null) ?: $duty['date'], $duty['end_time'] ?? null);

        // A same-day duty ending "before" it starts runs past midnight.
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    private function candidates(array $duty, ?string $ignoreId)
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $duty['date'])->subDay()->format('Y-m-d');
        $to = ($duty['end_date'] ?? null) ?: $duty['date'];

        // Duties starting before $from still clash if they run into it
        // (24-hour caretaker duties last months). Cancelled (not attended)
        // duties free their slot.
        return StaffDutySchedule::query()
            ->where('not_attended', '!=', true)
            ->where('date', '<=', $to)
            ->where(fn ($q) => $q->where('date', '>=', $from)->orWhere('end_date', '>=', $from))
            ->when($ignoreId, fn ($q) => $q->where('_id', '!=', $ignoreId));
    }

    public static function message(string $conflict): string
    {
        return $conflict === self::PATIENT
            ? 'This patient already has a staff member of this role scheduled at this time.'
            : 'This staff member is already scheduled at this time.';
    }

    /**
     * The conflict in words, naming the duty in the way: e.g. a caretaker
     * already living in with another patient, or a patient who already has
     * a caretaker. Roles differing (nurse and caretaker) never clash on a
     * patient, so both can be assigned to the same patient.
     */
    public function describe(string $conflict, array $duty, ?string $ignoreId = null): string
    {
        $query = $this->candidates($duty, $ignoreId);
        $query = $conflict === self::PATIENT
            ? $query->where('patient_id', $duty['patient_id'] ?? null)->when(! empty($duty['role']), fn ($q) => $q->where('role', $duty['role']))
            : $query->where('staff_id', $duty['staff_id'] ?? null);

        $clash = $query->get()->first(fn (StaffDutySchedule $other) => $this->overlaps($other->toArray(), $duty));
        if (! $clash) {
            return self::message($conflict);
        }

        $patient = Patient::where('username', $clash->patient_id)->value('patient_name') ?? $clash->patient_id;
        $when = $clash->live_in
            ? "from {$clash->date} to {$clash->end_date} (24-hour care)"
            : "on {$clash->date}, ".TimeFormat::to12Hour($clash->start_time).' – '.TimeFormat::to12Hour($clash->end_time);

        if ($conflict === self::PATIENT) {
            return $clash->live_in
                ? "{$patient} already has a caretaker {$when}."
                : "{$patient} already has a {$clash->role} scheduled {$when}.";
        }

        return $clash->live_in
            ? "This caretaker is already assigned to {$patient} {$when}. Reschedule or end that duty before assigning them to another patient."
            : "This staff member is already scheduled with {$patient} {$when}.";
    }
}
