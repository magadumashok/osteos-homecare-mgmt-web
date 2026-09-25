<?php

namespace App\Models;

/** The discharge summary written when a patient is discharged. */
class DischargedPatient extends TenantModel
{
    protected $table = 'discharged_patients';

    protected $fillable = ['patient_id', 'date', 'time', 'summary', 'discharged_by'];
}
