<?php

namespace App\Models;

/**
 * An enquiry from a prospective patient, before the initial assessment.
 * `lead_id` is a per-homecare sequence number the legacy clients use as
 * the lead's identifier.
 */
class PatientLead extends TenantModel
{
    public const FOLLOWUP_TO_BE_ASSESSED = 'to_be_assessed';

    public const FOLLOWUP_ASSESSMENT_DONE = 'assessment_done';

    protected $table = 'patient_leads';

    protected $fillable = [
        'lead_id', 'patient_name', 'mobile', 'email', 'country',
        'staff', 'source', 'date', 'time', 'followup', 'comments',
    ];
}
