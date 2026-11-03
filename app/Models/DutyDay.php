<?php

namespace App\Models;

/**
 * Sign in and sign out for one day of a 24-hour (live-in) duty: one
 * document per duty and date. The day is the date signed in on; signing
 * out may happen after midnight (e.g. at handover the next morning).
 *
 * `daily_salary` is the staff member's day rate when they signed out, so
 * a later change of rate does not alter days already worked.
 */
class DutyDay extends TenantModel
{
    protected $table = 'duty_day_attendance';

    protected $fillable = [
        'duty_id', 'patient_id', 'staff_id', 'date',
        'sign_in', 'signin_time', 'signin_latitude', 'signin_longitude',
        'sign_out', 'signout_time', 'signout_date', 'signout_latitude', 'signout_longitude',
        'daily_salary',
    ];
}
