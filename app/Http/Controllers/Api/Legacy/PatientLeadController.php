<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Services\PatientLeadService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Legacy lead endpoints (formerly in MobileUserController). */
class PatientLeadController extends Controller
{
    use ResponseTrait;

    private const FIELDS = ['patient_name', 'mobile', 'email', 'country', 'staff', 'source', 'date', 'time', 'followup', 'comments'];

    public function __construct(private readonly PatientLeadService $leads) {}

    /** POST /api/add_patient_lead */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['patient_name' => 'required', 'mobile' => 'required']);

        try {
            $this->leads->create($request->only(self::FIELDS));
        } catch (ValidationException) {
            // Legacy: a duplicate number is reported as "failed" with a 200.
            return $this->response(true, 'failed', 200);
        }

        return $this->response(true, 'success', 200, '');
    }

    /** GET /api/get_patient_leads */
    public function index(): JsonResponse
    {
        $leads = $this->leads->open();

        return $leads->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, ['patientleads' => $leads->toArray()]);
    }

    /** POST /api/get_lead_details {id} — returned as a one-element list. */
    public function show(Request $request): JsonResponse
    {
        $lead = $this->leads->find($request->input('id'));

        return $this->response(true, 'success', 200, ['patientlead' => $lead ? [$lead->toArray()] : []]);
    }

    /** POST /api/update-patient-lead-details {lead_id, ...} */
    public function update(Request $request): JsonResponse
    {
        $this->leads->update($this->leads->findOrFail($request->input('lead_id')), $request->only(self::FIELDS));

        return $this->response(true, 'success', 200);
    }

    /** POST /api/add_patient_to_be_assessed {lead_id} */
    public function markToBeAssessed(Request $request): JsonResponse
    {
        $this->leads->markToBeAssessed($this->leads->findOrFail($request->input('lead_id')));

        return $this->response(true, 'success', 200);
    }
}
