<?php

namespace App\Models;

/**
 * A duty of a nurse, physiotherapist, doctor or caretaker with a patient.
 * Dates are "Y-m-d" and times "HH:MM" strings; a duty may run past
 * midnight into `end_date`. `buffer_time` ("HH:MM") is travel time kept
 * free afterwards.
 *
 * Caretaker duties are `live_in`: the caretaker stays with the patient
 * around the clock from `date` to `end_date` (often months), so there are
 * no visit times; the duty occupies whole days. `care_tasks` lists what
 * the caretaker does each day, and `skipped_dates` the days they are away
 * (reasons and covers are in CareLog).
 */
class StaffDutySchedule extends TenantModel
{
    /** Fields an admin sets when creating or rescheduling a duty. */
    public const SCHEDULE_FIELDS = [
        'staff_id', 'role', 'patient_id', 'driver',
        'date', 'end_date', 'start_time', 'end_time', 'schedule_hour', 'buffer_time',
        'care_tasks',
    ];

    /** Roles whose duties are 24-hour, multi-day stays. */
    public const LIVE_IN_ROLES = ['caretaker'];

    protected $table = 'staff_duty_schedule';

    protected $fillable = [
        ...self::SCHEDULE_FIELDS,
        'service_package', 'fees', 'services',
        'sign_in', 'signin_time', 'signin_latitude', 'signin_longitude',
        'sign_out', 'signout_time', 'signout_latitude', 'signout_longitude',
        'not_attended', 'not_attended_reason', 'specimen_images',
        'live_in', 'skipped_dates', 'covers_duty_id',
    ];

    /** Attendance fields every new or rescheduled duty starts with. */
    public static function freshAttendance(): array
    {
        return [
            'sign_in' => false, 'signin_time' => '',
            'sign_out' => false, 'signout_time' => '',
            'not_attended' => false, 'not_attended_reason' => '',
        ];
    }

    public static function isLiveInRole(?string $role): bool
    {
        return in_array($role, self::LIVE_IN_ROLES, true);
    }

    /**
     * Whole days, no driver or travel buffer: what a live-in duty stores in
     * place of visit times, so conflict checks and time-sorted lists keep
     * working unchanged.
     */
    public static function liveInFields(): array
    {
        return [
            'live_in' => true,
            'start_time' => '00:00', 'end_time' => '23:59',
            'schedule_hour' => '24:00', 'buffer_time' => '00:00',
            'driver' => null,
        ];
    }

    public function covers(string $date): bool
    {
        return $this->date <= $date && ($this->end_date ?: $this->date) >= $date;
    }

    public function isSkipped(string $date): bool
    {
        return in_array($date, (array) ($this->skipped_dates ?? []), true);
    }
}
