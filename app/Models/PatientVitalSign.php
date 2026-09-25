<?php

namespace App\Models;

class PatientVitalSign extends TenantModel
{
    /** Readings a nurse may record; any may be left out. */
    public const MEASUREMENTS = [
        'patient_bp_systolic_value', 'patient_bp_diastolic_value',
        'temperature', 'pulse', 'resp', 'sat_O2', 'intake', 'output',
    ];

    protected $table = 'patient_vital_signs';

    protected $fillable = ['nurse_id', 'nurse_name', 'patient_id', 'date', 'time', ...self::MEASUREMENTS];
}
