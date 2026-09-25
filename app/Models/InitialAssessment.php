<?php

namespace App\Models;

/**
 * The initial home assessment of a lead. `assessment_id` is the lead_id it
 * was made from. Admitting the patient copies these fields to `patients`
 * and deletes the assessment, as the legacy app did.
 */
class InitialAssessment extends TenantModel
{
    /** Fields carried over to the patient record on admission. */
    public const CLINICAL_FIELDS = [
        'assessment_id', 'patient_name', 'mobile', 'email', 'identity_number',
        'birth_date', 'age', 'gender', 'address', 'nationality',
        'physician', 'diagnosis', 'diagnosis_name', 'physical_assessment',
        'daily_activities', 'equipment', 'medications', 'care_plan',
        'nursing', 'physiotherapy', 'physiotherapy_details',
        'physiotherapy_treatment_plan', 'doctor', 'invoice_details',
        'document_type', 'docfilepath',
        'terms_conditions', 'consent_form', 'amount_paid', 'digitalsignature',
    ];

    protected $table = 'initial_assessment';

    protected $fillable = self::CLINICAL_FIELDS;
}
