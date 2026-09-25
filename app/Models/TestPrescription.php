<?php

namespace App\Models;

class TestPrescription extends TenantModel
{
    protected $table = 'test_prescriptions';

    protected $fillable = ['patient_id', 'doctor_id', 'date', 'prescription_data'];
}
