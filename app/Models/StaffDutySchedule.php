<?php

namespace App\Models;

/**
 * A duty of a nurse, physiotherapist or doctor with a patient. Dates are
 * "Y-m-d" and times "HH:MM" strings; a duty may run past midnight into
 * `end_date`. `buffer_time` ("HH:MM") is travel time kept free afterwards.
 */
class StaffDutySchedule extends TenantModel
{
    /** Fields an admin sets when creating or rescheduling a duty. */
    public const SCHEDULE_FIELDS = [
        'staff_id', 'role', 'patient_id', 'driver',
        'date', 'end_date', 'start_time', 'end_time', 'schedule_hour', 'buffer_time',
    ];

    protected $table = 'staff_duty_schedule';

    protected $fillable = [
        ...self::SCHEDULE_FIELDS,
        'service_package', 'fees', 'services',
        'sign_in', 'signin_time', 'signin_latitude', 'signin_longitude',
        'sign_out', 'signout_time', 'signout_latitude', 'signout_longitude',
        'not_attended', 'not_attended_reason', 'specimen_images',
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
}
