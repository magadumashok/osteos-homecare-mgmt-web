<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Models\InitialAssessment;
use App\Services\AssessmentService;
use App\Services\PatientService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Legacy assessment and admission endpoints (formerly in MobileUserController). */
class AssessmentController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly AssessmentService $assessments) {}

    /**
     * POST /api/save-assessment-details
     * Multipart: `formdata` is a JSON string of the form, `document` a file.
     */
    public function store(Request $request): JsonResponse
    {
        $form = json_decode((string) $request->input('formdata'), true);
        $data = is_array($form) ? [...$request->except(['formdata', 'document']), ...$form] : $request->except('document');

        if (($data['lead_id'] ?? null) !== null && $this->assessments->existsForLead($data['lead_id'])) {
            return $this->response(true, 'patient already assessed', 200, '');
        }

        $this->assessments->create($data, $request->file('document'));

        return $this->response(true, 'success', 200, '');
    }

    /** GET /api/get-all-assessed-patients */
    public function index(): JsonResponse
    {
        $assessments = $this->assessments->all();

        return $assessments->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, ['assessedpatients' => $assessments->toArray()]);
    }

    /** GET /api/get-assessment-details/{id} and /api/get-assessment-invoice/{id} */
    public function show(string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->assessments->find($id));
    }

    /** POST /api/update-assessment-details {formdata: {_id, ...}} */
    public function update(Request $request): JsonResponse
    {
        $form = (array) $request->input('formdata', []);
        $assessment = $this->assessments->findOrFail((string) ($form['_id'] ?? ''));

        $this->assessments->update($assessment, array_intersect_key($form, array_flip(InitialAssessment::CLINICAL_FIELDS)));

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/create-homecare-patient {formProps: {_id, ...}} — admits the assessed patient. */
    public function admit(Request $request): JsonResponse
    {
        $form = (array) $request->input('formProps', []);
        $assessment = $this->assessments->findOrFail((string) ($form['_id'] ?? ''));

        $this->assessments->admit($assessment, $form);

        return $this->response(true, 'success', 200);
    }

    /**
     * POST /api/patients-digital-signature
     * {username, terms_conditions, consent_form, amount_paid, image (base64 PNG)}
     */
    public function consent(Request $request, PatientService $patients): JsonResponse
    {
        $fileName = $patients->recordConsent(
            $patients->findOrFail($request->input('username')),
            $request->only(['terms_conditions', 'consent_form', 'amount_paid']),
            $request->input('image'),
        );

        return $this->response(true, 'success', 200, $fileName);
    }
}
