<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Services\DutyScheduleService;
use App\Services\PatientDocumentService;
use App\Services\PatientService;
use App\Services\StaffService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientDocumentController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly PatientDocumentService $documents,
        private readonly PatientService $patients,
    ) {}

    public function index(string $username): JsonResponse
    {
        $patient = $this->patients->findOrFail($username);

        return $this->response(true, '', 200, $patient->only(['username', 'patient_name', ...array_keys(PatientDocumentService::FOLDERS)]));
    }

    /** Upload `files[]` to `field`: images (documents), emirates_id or specimen_images. */
    public function store(Request $request, string $username): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys(PatientDocumentService::FOLDERS))],
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', Rules::upload()],
        ]);

        $urls = $this->documents->add($this->patients->findOrFail($username), $data['field'], $request->file('files'));

        return $this->response(true, 'Uploaded.', 201, $urls);
    }

    /** Specimen photos from a duty, tagged with lab details. */
    public function storeSpecimens(Request $request, string $username, DutyScheduleService $duties): JsonResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['image', Rules::upload()],
            'lab_details' => ['nullable', 'string', 'max:1000'],
            'schedule_id' => ['nullable', 'string'],
        ]);

        $duty = isset($data['schedule_id']) ? $duties->findOrFail($data['schedule_id']) : null;
        $entries = $this->documents->addSpecimens(
            $this->patients->findOrFail($username), $duty, $request->file('files'), $data['lab_details'] ?? null,
        );

        return $this->response(true, 'Uploaded.', 201, $entries);
    }

    public function destroy(Request $request, string $username): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string'],
            'field' => ['nullable', Rule::in(array_keys(PatientDocumentService::FOLDERS))],
        ]);

        $this->documents->remove($this->patients->findOrFail($username), $data['url'], $data['field'] ?? 'images');

        return $this->response(true, 'Document removed.');
    }

    /** Ask the nurses (by email) for a document missing from the patient's file. */
    public function requestMissing(Request $request, string $username, StaffService $staff): JsonResponse
    {
        $message = $request->validate(['message' => ['required', 'string', 'max:2000']])['message'];
        $patient = $this->patients->findOrFail($username);

        $sent = $staff->messageRole('nurse', $request->user(), "Missing document for {$patient->patient_name}", $message);

        return $this->response(true, "Sent to {$sent} nurse(s).");
    }
}
