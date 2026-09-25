<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\PatientLeadRequest;
use App\Services\PatientLeadService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;

class PatientLeadController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly PatientLeadService $leads) {}

    /** Leads not yet assessed. */
    public function index(): JsonResponse
    {
        return $this->response(true, '', 200, $this->leads->open());
    }

    public function store(PatientLeadRequest $request): JsonResponse
    {
        return $this->response(true, 'Lead added.', 201, $this->leads->create($request->validated()));
    }

    public function show(string $leadId): JsonResponse
    {
        return $this->response(true, '', 200, $this->leads->findOrFail($leadId));
    }

    public function update(PatientLeadRequest $request, string $leadId): JsonResponse
    {
        $lead = $this->leads->update($this->leads->findOrFail($leadId), $request->validated());

        return $this->response(true, 'Lead updated.', 200, $lead);
    }

    public function markToBeAssessed(string $leadId): JsonResponse
    {
        $this->leads->markToBeAssessed($this->leads->findOrFail($leadId));

        return $this->response(true, 'Lead moved to assessment.');
    }
}
