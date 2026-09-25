<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\DutyScheduleService;
use App\Services\PatientService;
use App\Services\ReferenceDataService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy patient endpoints (formerly in MobileUserController and
 * AdmittedPatientsController). The Redis patient cache is gone: its keys
 * were not per homecare, so centers could see each other's patients.
 */
class PatientController extends Controller
{
    use ResponseTrait;

    private const REGISTER_FIELDS = [
        'patient_name', 'mobile', 'gender', 'source_of_contact', 'status', 'id_no', 'insurance_name',
        'weight', 'date_of_birth', 'age', 'email', 'country', 'nationality', 'state', 'service',
        'service_package', 'fees', 'address', 'date', 'time', 'comments',
    ];

    public function __construct(private readonly PatientService $patients) {}

    /** POST /api/register_patient */
    public function register(Request $request): JsonResponse
    {
        $request->validate(['patient_name' => 'required']);

        $this->patients->register($request->only(self::REGISTER_FIELDS));

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/save_walkin_schedule — the signed-in staff member's walk-in patient and visit. */
    public function registerWalkIn(Request $request): JsonResponse
    {
        $request->validate(['patient_name' => 'required', 'date' => 'required', 'start_time' => 'required', 'end_time' => 'required']);

        $this->patients->registerWalkIn(
            $request->user(),
            $request->only(self::REGISTER_FIELDS),
            $request->only(['role', 'date', 'end_date', 'start_time', 'end_time', 'schedule_hour', 'buffer_time']),
        );

        return $this->response(true, 'success', 200, '');
    }

    /** GET /api/get-admitted-patients — every patient, discharged or not. */
    public function index(): JsonResponse
    {
        $patients = $this->patients->all();

        return $patients->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'dynamodb data', 200, $patients);
    }

    /** GET /api/get-all-confirmed-patients — patients not yet discharged. */
    public function admitted(): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->patients->admitted());
    }

    /**
     * POST /api/get_patient_data {id}, GET /api/get-patient-details/{id},
     * GET /api/get-admitted-patient-invoice/{id}, GET /api/patients/{id}/get-patients-documents
     */
    public function show(Request $request, ?string $id = null): JsonResponse
    {
        return $this->response(true, '', 200, $this->patients->find($id ?? $request->input('id')));
    }

    /** POST /api/update-patient-details {patient_id, ...fields} */
    public function update(Request $request): JsonResponse
    {
        $this->patients->update(
            $this->patients->findOrFail($request->input('patient_id')),
            $request->only(Patient::EDITABLE_FIELDS),
        );

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/update_service_package {schedule_id, patient_id, service_data} */
    public function updateServices(Request $request, DutyScheduleService $duties): JsonResponse
    {
        $request->validate(['patient_id' => 'required', 'service_data' => 'required|array']);

        $duty = $request->filled('schedule_id') ? $duties->findOrFail($request->input('schedule_id')) : null;
        $this->patients->updateServices($this->patients->findOrFail($request->input('patient_id')), $duty, $request->input('service_data'));

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/add-discharge-details {formProps: {patient_id, date, time}} */
    public function scheduleDischarge(Request $request): JsonResponse
    {
        $form = (array) $request->input('formProps', []);

        $this->patients->scheduleDischarge($this->patients->findOrFail($form['patient_id'] ?? null), (string) ($form['date'] ?? ''), $form['time'] ?? null);

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/discharge-patient-by-nurse {patient_id, date, time, summary} */
    public function discharge(Request $request): JsonResponse
    {
        $this->patients->discharge(
            $this->patients->findOrFail($request->input('patient_id')),
            $request->user(),
            $request->only(['date', 'time', 'summary']),
        );

        return $this->response(true, 'success', 200, '');
    }

    /** GET /api/get-all-discharged-patients */
    public function discharged(): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->patients->discharged());
    }

    /** GET /api/get-confirmed-patients-for-discharge-for-nurse */
    public function awaitingDischarge(Request $request): JsonResponse
    {
        $patients = $this->patients->awaitingDischargeFor($request->user());

        return $patients->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, $patients);
    }

    /** POST /api/get-patients-from_nurse-duty {staffSelected} */
    public function ofStaff(Request $request): JsonResponse
    {
        $patients = $this->patients->patientsOfStaff((string) $request->input('staffSelected'));

        return $patients->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, $patients);
    }

    /** GET /api/get-icd-codes?search= — codes wrapped in a one-element list, as before. */
    public function icdCodes(Request $request, ReferenceDataService $reference): JsonResponse
    {
        return $this->response(true, '', 200, [$reference->searchIcdCodes((string) $request->query('search'))]);
    }

    /** GET /api/get_service_packages — bare array. */
    public function servicePackages(ReferenceDataService $reference): JsonResponse
    {
        return response()->json($reference->servicePackages());
    }
}
