<?php

namespace App\Models;

/** A one-off visit by a staff member to someone who is not an admitted patient. */
class StaffShortVisit extends TenantModel
{
    protected $table = 'staff_short_visit';

    protected $fillable = [
        'staff_id', 'staff_role',
        'patient_name', 'patient_mobile', 'patient_email', 'patient_country', 'patient_address',
        'date', 'time', 'visit_purpose', 'visit_fees',
        'sign_in', 'signin_time', 'sign_out', 'signout_time',
        'not_attended', 'not_attended_reason',
    ];
}
