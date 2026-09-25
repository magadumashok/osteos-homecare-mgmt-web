<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Models\PatientVitalSign;
use App\Services\MedicationService;
use App\Services\NoteService;
use App\Services\VitalSignService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy vitals, medication and notes endpoints (formerly in
 * MobileUserController and AdmittedPatientsController).
 */
class ClinicalController extends Controller
{
    use ResponseTrait;

    // ---------------------------------------------------------------------
    // Vital signs
    // ---------------------------------------------------------------------

    /** POST /api/store_vitalsigns */
    public function storeVitals(Request $request, VitalSignService $vitals): JsonResponse
    {
        $request->validate(['patient_id' => 'required', 'date' => 'required', 'time' => 'required']);

        $vitals->store($request->only(['nurse_id', 'nurse_name', 'patient_id', 'date', 'time', ...PatientVitalSign::MEASUREMENTS]));

        return $this->response(true, 'success', 200, '');
    }

    /** GET /api/patients/{id}/{time_range}/get-patients-vitalsigns */
    public function vitalsInRange(string $id, string $range, VitalSignService $vitals): JsonResponse
    {
        return $this->vitalsResponse($vitals->inRange($id, $range));
    }

    /** GET /api/patients/{id}/{date}/get-patients-vital-signs-bydate (the legacy version ignored the date). */
    public function vitalsOnDate(string $id, string $date, VitalSignService $vitals): JsonResponse
    {
        return $this->vitalsResponse($vitals->onDate($id, $date));
    }

    /** GET /api/patients/{id}/get-latest-vitalsigns-data */
    public function latestVitals(string $id, VitalSignService $vitals): JsonResponse
    {
        $latest = $vitals->latest($id);

        return $latest
            ? $this->response(true, 'success', 200, $latest)
            : $this->response(true, 'no data found', 200, []);
    }

    /** POST /api/patients/update-patients-vitalsigns {formdata: {_id, ...}} */
    public function updateVitals(Request $request, VitalSignService $vitals): JsonResponse
    {
        $form = (array) $request->input('formdata', []);
        $reading = $vitals->findOrFail((string) ($form['_id'] ?? ''));

        $vitals->update($reading, array_intersect_key($form, array_flip(['date', 'time', ...PatientVitalSign::MEASUREMENTS])));

        return $this->response(true, 'success', 200, '');
    }

    // ---------------------------------------------------------------------
    // Medication
    // ---------------------------------------------------------------------

    /** POST /api/add_medication_schedule */
    public function addMedication(Request $request, MedicationService $medications): JsonResponse
    {
        $request->validate(['patient_id' => 'required', 'medicine_name' => 'required', 'date' => 'required']);

        $medications->addSchedule($request->only([
            'patient_id', 'medicine_name', 'dosage', 'dose_unit', 'frequency', 'before_or_after_food', 'date', 'time', 'entered_by',
        ]), $request->user());

        return $this->response(true, 'success', 200, '');
    }

    /** GET /api/get-patient-medication-schedule/{id} */
    public function medicationSchedule(string $id, MedicationService $medications): JsonResponse
    {
        return $this->response(true, 'success', 200, $medications->scheduleFor($id));
    }

    /** POST /api/get_administer_medication_list {patient_id, date} */
    public function medicationChecklist(Request $request, MedicationService $medications): JsonResponse
    {
        $list = $medications->checklist((string) $request->input('patient_id'), (string) $request->input('date'));

        return $list->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, $list);
    }

    /** GET /api/get-administer-medications/{id}/{date} */
    public function administeredOn(string $id, string $date, MedicationService $medications): JsonResponse
    {
        return $this->response(true, 'success', 200, $medications->administeredOn($id, $date));
    }

    /** POST /api/update-medicine-details */
    public function administer(Request $request, MedicationService $medications): JsonResponse
    {
        $request->validate(['patient_id' => 'required', 'medicine_name' => 'required', 'date' => 'required', 'medicine_type' => 'required']);

        $medications->recordAdministration($request->all(), $request->user());

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/get_all_guardians {patient_id} — bare array. */
    public function guardians(Request $request, MedicationService $medications): JsonResponse
    {
        return response()->json($medications->guardians((string) $request->input('patient_id')));
    }

    // ---------------------------------------------------------------------
    // Notes
    // ---------------------------------------------------------------------

    /** POST /api/store_nurse_notes {patient_id, nurse_id, nurse_name, date, time, notes | audio} */
    public function storeNursingNote(Request $request, NoteService $notes): JsonResponse
    {
        return $this->storeNote('nursing', $request, $notes);
    }

    /** POST /api/store_staff_notes {patient_id, staff_id, staff_name, staff_role, date, time, notes | audio} */
    public function storeStaffNote(Request $request, NoteService $notes): JsonResponse
    {
        return $this->storeNote('staff', $request, $notes);
    }

    /** POST /api/upload-nursing-note-image-by-nurse (document) */
    public function storeNursingImage(Request $request, NoteService $notes): JsonResponse
    {
        return $this->storeImageNote('nursing', $request, $notes);
    }

    /** POST /api/upload-image-note-by-nurse (document) — a staff image note. */
    public function storeStaffImage(Request $request, NoteService $notes): JsonResponse
    {
        return $this->storeImageNote('staff', $request, $notes);
    }

    /** GET /api/patients/{id}/{type}/get-nurse-notes — today's notes. */
    public function nursingNotesToday(string $id, string $type, NoteService $notes): JsonResponse
    {
        return $this->notesResponse($notes->today('nursing', $id, $type));
    }

    /** GET /api/patients/{id}/{type}/get-staff-notes — today's notes. */
    public function staffNotesToday(string $id, string $type, NoteService $notes): JsonResponse
    {
        return $this->notesResponse($notes->today('staff', $id, $type));
    }

    /** GET /api/get-staff-notes-by-date/{id}/{date}/{notes_type} (the legacy version ignored the date). */
    public function staffNotesOnDate(string $id, string $date, string $type, NoteService $notes): JsonResponse
    {
        return $this->response(true, 'success', 200, $notes->staffNotesWithAuthors($id, $date, $type));
    }

    private function storeNote(string $kind, Request $request, NoteService $notes): JsonResponse
    {
        $request->validate(['patient_id' => 'required']);

        $notes->store($kind, $request->except('document'), $request->user());

        return $this->response(true, 'success', 200, '');
    }

    private function storeImageNote(string $kind, Request $request, NoteService $notes): JsonResponse
    {
        $request->validate(['patient_id' => 'required', 'document' => 'required|file|image']);

        $notes->storeImage($kind, $request->except('document'), $request->file('document'), $request->user());

        return $this->response(true, 'success', 200, '');
    }

    private function vitalsResponse($readings): JsonResponse
    {
        return $readings->isEmpty()
            ? $this->response(true, 'failed', 200, [])
            : $this->response(true, 'success', 200, $readings);
    }

    private function notesResponse($notes): JsonResponse
    {
        return $notes->isEmpty()
            ? $this->response(true, 'failed', 200, '')
            : $this->response(true, 'success', 200, $notes);
    }
}
