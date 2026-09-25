<?php

namespace App\Models;

/** A medicine to give a patient on a date. `entered_by` is the staff username. */
class MedicationSchedule extends TenantModel
{
    protected $table = 'medication_schedule';

    protected $fillable = [
        'patient_id', 'medicine_name', 'dosage', 'dose_unit', 'frequency',
        'before_or_after_food', 'date', 'time', 'entered_by',
    ];
}
