<?php

namespace App\Models;

/** A family member who witnessed and signed for administered medication. */
class PatientGuardian extends TenantModel
{
    protected $table = 'patient_guardians';

    protected $fillable = ['patient_id', 'guardian_id', 'guardian_name', 'guardian_signature'];
}
