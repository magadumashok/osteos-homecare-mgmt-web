<?php

namespace App\Models;

/**
 * One day of a caretaker's 24-hour (live-in) duty: which care tasks were
 * done, symptoms noticed and notes, or why the caretaker skipped the day
 * and who covered it. One document per duty and date.
 *
 * `tasks` is a list of {name, done, time, note}. `status` is "logged" or
 * "skipped"; a skipped day may carry `covered_by` (the replacement
 * caretaker's username) and `cover_duty_id`.
 */
class CareLog extends TenantModel
{
    public const LOGGED = 'logged';

    public const SKIPPED = 'skipped';

    /** Tasks offered when scheduling a caretaker; admins can add their own per duty. */
    public const DEFAULT_TASKS = [
        'Bathing',
        'Feeding',
        'Preparing meals',
        'Laundry',
        'Getting groceries',
        'Reminding to take medication',
        'Reporting symptoms',
        'Mobility / walking assistance',
        'Toileting',
        'Housekeeping',
    ];

    protected $table = 'care_taker_services';

    protected $fillable = [
        'duty_id', 'patient_id', 'staff_id', 'staff_name', 'date', 'status',
        'tasks', 'symptoms', 'notes', 'updated_time',
        'skip_reason', 'covered_by', 'cover_duty_id',
    ];
}
