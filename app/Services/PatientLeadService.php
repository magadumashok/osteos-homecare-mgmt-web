<?php

namespace App\Services;

use App\Models\PatientLead;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PatientLeadService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly ReferenceDataService $reference,
    ) {}

    /** Leads not yet assessed. */
    public function open(): Collection
    {
        return PatientLead::where('followup', '!=', PatientLead::FOLLOWUP_ASSESSMENT_DONE)->get();
    }

    public function find(int|string|null $leadId): ?PatientLead
    {
        return $leadId === null || $leadId === '' ? null : $this->byLeadId($leadId)->first();
    }

    public function findOrFail(int|string|null $leadId): PatientLead
    {
        return $this->find($leadId) ?? throw (new ModelNotFoundException)->setModel(PatientLead::class, [$leadId]);
    }

    /**
     * `mobile` is the local number; the country's dialling code is prepended.
     *
     * @throws ValidationException when a lead with this number exists
     */
    public function create(array $data): PatientLead
    {
        $mobile = $this->reference->internationalNumber($data['country'] ?? null, $data['mobile'] ?? null);

        if ($mobile !== null && PatientLead::where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages(['mobile' => 'A lead with this mobile number already exists.']);
        }

        return PatientLead::create([
            ...$data,
            'mobile' => $mobile,
            'lead_id' => $this->sequences->next('patient_lead', fn () => (int) PatientLead::max('lead_id')),
        ]);
    }

    public function update(PatientLead $lead, array $data): PatientLead
    {
        $lead->update($data);

        return $lead;
    }

    public function markToBeAssessed(PatientLead $lead): void
    {
        $lead->update(['followup' => PatientLead::FOLLOWUP_TO_BE_ASSESSED]);
    }

    public function markAssessed(PatientLead $lead): void
    {
        $lead->update(['followup' => PatientLead::FOLLOWUP_ASSESSMENT_DONE]);
    }

    /** Migrated lead_ids are numbers; clients send them as strings. Match either. */
    private function byLeadId(int|string $leadId)
    {
        $leadId = trim((string) $leadId);

        return PatientLead::where(function ($q) use ($leadId) {
            $q->where('lead_id', $leadId);
            if (ctype_digit($leadId)) {
                $q->orWhere('lead_id', (int) $leadId);
            }
        });
    }
}
