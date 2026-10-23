<?php

namespace App\Services;

use App\Models\InitialAssessment;
use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Lead → initial assessment → admitted patient.
 */
class AssessmentService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly PatientLeadService $leads,
        private readonly PatientService $patients,
        private readonly FileStorageService $files,
    ) {}

    public function all(): Collection
    {
        return InitialAssessment::orderBy('created_at', 'desc')->get();
    }

    public function find(string $id): ?InitialAssessment
    {
        return InitialAssessment::where('_id', $id)->first();
    }

    public function findOrFail(string $id): InitialAssessment
    {
        return InitialAssessment::where('_id', $id)->firstOrFail();
    }

    public function existsForLead(int|string $leadId): bool
    {
        $leadId = trim((string) $leadId);
        $ids = ctype_digit($leadId) ? [$leadId, (int) $leadId] : [$leadId];

        return InitialAssessment::whereIn('assessment_id', $ids)->exists();
    }

    /**
     * Record the assessment of a lead and mark the lead assessed.
     * `lead_id` becomes the assessment_id; `country` is stored as the
     * nationality, as the legacy form sent it.
     *
     * @throws ValidationException when the lead was already assessed
     */
    public function create(array $data, ?UploadedFile $document = null): InitialAssessment
    {
        $leadId = $data['lead_id'] ?? null;

        if ($leadId !== null && $this->existsForLead($leadId)) {
            throw ValidationException::withMessages(['lead_id' => 'This patient has already been assessed.']);
        }

        $attributes = array_intersect_key($data, array_flip(InitialAssessment::CLINICAL_FIELDS));
        $attributes['assessment_id'] = $leadId;
        if (isset($data['country']) && ! isset($attributes['nationality'])) {
            $attributes['nationality'] = $data['country'];
        }

        if ($document) {
            $attributes['docfilepath'] = $this->files->storeUpload($document, "assessments/{$leadId}/documents");
        }

        $assessment = InitialAssessment::create([
            ...$attributes,
            'terms_conditions' => false,
            'consent_form' => false,
            'amount_paid' => false,
        ]);

        if ($lead = $this->leads->find($leadId)) {
            $this->leads->markAssessed($lead);
        }

        return $assessment;
    }

    public function update(InitialAssessment $assessment, array $data): InitialAssessment
    {
        unset($data['_id'], $data['assessment_id']);
        $assessment->update($data);

        return $assessment;
    }

    /**
     * Admit the assessed patient: copy the assessment (with any corrections
     * from the admission form) into `patients` and delete the assessment.
     * The patient keeps the assessment's _id, as in the legacy app.
     */
    public function admit(InitialAssessment $assessment, array $overrides = []): Patient
    {
        \Log::info('Admitting patient from assessment', [
            'assessment_id' => $assessment->getKey(),
            'overrides' => $overrides,
        ]);

         \Log::info( $assessment );
        $fields = array_flip(InitialAssessment::CLINICAL_FIELDS);
        // $mobile_modified = substr($assessment["mobile"], 3);
        // $assessment["mobile"] = $mobile_modified;

        $patient = new Patient([
            ...array_intersect_key($assessment->getAttributes(), $fields),
            ...array_intersect_key($overrides, $fields),
            // 'username' => $this->patients->nextUsername($assessment->getAttributes()),
            'username' => $this->generateUsername($assessment->getAttribute('patient_name'), $assessment->getAttribute('mobile')),
            'discharged' => false,
        ]);
        $patient->setAttribute('_id', $assessment->getKey());
        $patient->save();

        $assessment->delete();

        return $patient;
    }

    public function generateUsername(string $firstname, string $mobile): string
    {
        $mobile_modified = substr($mobile, 3);
        $prefix = substr((string) $this->tenants->current(), 0, 3).'p';
        $exists = Patient::where('mobile', $mobile)->exists();
        return $exists
            ? $prefix . substr($firstname, 0, 3) . $mobile_modified
            : $prefix . $mobile_modified;
    }
}
