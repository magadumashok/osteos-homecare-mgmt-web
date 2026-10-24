<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;

/**
 * A registered (admitted) patient. `username` ("<prefix>p001") is the id
 * every other collection refers to as `patient_id`.
 *
 * The legacy app had two models (Patient, AdmittedPatient) on this one
 * collection; they are the same thing.
 */
class Patient extends TenantModel
{
    /** Array fields that hold uploaded file URLs. */
    public const DOCUMENT_FIELDS = ['images', 'emirates_id', 'specimen_images'];

    /** Fields staff may edit on an existing patient. */
    public const EDITABLE_FIELDS = [
        'patient_name', 'mobile', 'email', 'gender', 'age', 'date_of_birth', 'birth_date',
        'identity_number', 'id_no', 'nationality', 'insurance_name', 'weight',
        'country', 'state', 'city', 'address', 'address_link',
        'source_of_contact', 'status', 'comments',
        'service', 'service_package', 'fees', 'services',
        'physician', 'diagnosis', 'diagnosis_name', 'physical_assessment',
        'daily_activities', 'equipment', 'medications', 'care_plan',
        'nursing', 'physiotherapy', 'physiotherapy_details',
        'physiotherapy_treatment_plan', 'doctor', 'invoice_details', 'document_type',
    ];

    protected $table = 'patients';

    protected $fillable = [
        ...self::EDITABLE_FIELDS,
        'username', 'lead_id', 'assessment_id', 'date', 'time', 'docfilepath',
        'terms_conditions', 'consent_form', 'amount_paid', 'digitalsignature',
        ...self::DOCUMENT_FIELDS,
        'discharged', 'discharge',
    ];

    /** Patients not yet discharged. */
    public function scopeAdmitted(Builder $query): Builder
    {
        return $query->where('discharged', '!=', true);
    }
}
