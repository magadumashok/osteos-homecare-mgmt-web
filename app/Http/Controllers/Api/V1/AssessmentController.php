<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessments\AssessmentRequest;
use App\Models\InitialAssessment;
use App\Services\AssessmentService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly AssessmentService $assessments) {}

    public function index(): JsonResponse
    {
        return $this->response(true, '', 200, $this->assessments->all());
    }

    public function store(AssessmentRequest $request): JsonResponse
    {
        $assessment = $this->assessments->create($request->safe()->except('document'), $request->file('document'));

        return $this->response(true, 'Assessment saved.', 201, $assessment);
    }

    public function show(string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->assessments->findOrFail($id));
    }

    public function update(AssessmentRequest $request, string $id): JsonResponse
    {
        $assessment = $this->assessments->update($this->assessments->findOrFail($id), $request->safe()->except('document'));

        return $this->response(true, 'Assessment updated.', 200, $assessment);
    }

    /** Admit the assessed patient. Fields sent here correct the assessment's values. */
    public function admit(Request $request, string $id): JsonResponse
    {
        $overrides = $request->only(InitialAssessment::CLINICAL_FIELDS);
        $patient = $this->assessments->admit($this->assessments->findOrFail($id), $overrides);

        return $this->response(true, 'Patient admitted.', 201, $patient);
    }
}
