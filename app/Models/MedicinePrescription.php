<?php

namespace App\Models;

class MedicinePrescription extends TenantModel
{
    protected $table = 'medicine_prescriptions';

    protected $fillable = ['patient_id', 'doctor_id', 'date', 'prescription_data'];
}
