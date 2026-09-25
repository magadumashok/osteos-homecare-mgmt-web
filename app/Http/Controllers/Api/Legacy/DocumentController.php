<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Services\DutyScheduleService;
use App\Services\PatientDocumentService;
use App\Services\PatientService;
use App\Services\StaffService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy patient document endpoints (formerly in MobileUserController,
 * AdmittedPatientsController and DoctorController). Seven near-identical
 * upload methods now share PatientDocumentService.
 */
class DocumentController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly PatientDocumentService $documents,
        private readonly PatientService $patients,
    ) {}

    /** POST /api/upload-patient-document-by-staff and -by-doctor {patient_id, document} */
    public function uploadDocument(Request $request): JsonResponse
    {
        return $this->upload($request, 'images', 'document');
    }

    /** POST /api/upload-patient-documents-by-staff {patient_id, documents[]} */
    public function uploadDocuments(Request $request): JsonResponse
    {
        return $this->upload($request, 'images', 'documents');
    }

    /** POST /api/upload-emirates-id-by-staff {patient_id, document} */
    public function uploadEmiratesId(Request $request): JsonResponse
    {
        return $this->upload($request, 'emirates_id', 'document');
    }

    /** POST /api/upload-specimen-image-by-staff {patient_id, document} */
    public function uploadSpecimen(Request $request): JsonResponse
    {
        return $this->upload($request, 'specimen_images', 'document');
    }

    /** POST /api/upload-specimen-images-by-staff {patient_id, schedule_id, lab_details, documents[]} */
    public function uploadSpecimens(Request $request, DutyScheduleService $duties): JsonResponse
    {
        $request->validate(['patient_id' => 'required', 'documents' => 'array', 'documents.*' => ['file', Rules::upload()]]);

        if ($request->hasFile('documents')) {
            $duty = $request->filled('schedule_id') ? $duties->findOrFail((string) $request->input('schedule_id')) : null;
            $this->documents->addSpecimens(
                $this->patients->findOrFail($request->input('patient_id')),
                $duty,
                $request->file('documents'),
                $request->input('lab_details'),
            );
        }

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/delete-document {pid, imgurl} */
    public function destroy(Request $request): JsonResponse
    {
        $this->documents->remove($this->patients->findOrFail($request->input('pid')), (string) $request->input('imgurl'));

        return $this->response(true, 'Image has been deleted successfully...', 200, '');
    }

    /** POST /api/missing-document-notification {patient_name, message} — emails the nurses. */
    public function requestMissing(Request $request, StaffService $staff): JsonResponse
    {
        $request->validate(['message' => 'required']);

        $staff->messageRole('nurse', $request->user(), 'Missing document for '.$request->input('patient_name'), (string) $request->input('message'));

        return $this->response(true, 'success', 200, '');
    }

    private function upload(Request $request, string $field, string $input): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required',
            ($input === 'documents' ? 'documents.*' : $input) => ['file', Rules::upload()],
        ]);

        if ($request->hasFile($input)) {
            $files = $request->file($input);
            $this->documents->add($this->patients->findOrFail($request->input('patient_id')), $field, is_array($files) ? $files : [$files]);
        }

        return $this->response(true, 'success', 200, '');
    }
}
