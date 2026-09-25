<?php

namespace App\Models;

/**
 * Nurse duties from before the legacy app moved every role into
 * `staff_duty_schedule`. Kept for migrated history; nothing writes here.
 */
class NurseDutySchedule extends TenantModel
{
    protected $table = 'nurse_duty_schedule';
}
