<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Services\PrescriptionService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Legacy prescription endpoints (formerly DoctorController). */
class PrescriptionController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly PrescriptionService $prescriptions) {}

    /** POST /api/add-medicine-prescription {patient_id, date, prescription_data} */
    public function storeMedicine(Request $request): JsonResponse
    {
        return $this->store('medicine', $request);
    }

    /** POST /api/add-test-prescription {patient_id, date, prescription_data} */
    public function storeTest(Request $request): JsonResponse
    {
        return $this->store('test', $request);
    }

    /** POST /api/get-medicine-prescriptions {patient_id} */
    public function medicines(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->prescriptions->forPatient('medicine', (string) $request->input('patient_id')));
    }

    /** POST /api/get-test-prescriptions {patient_id} */
    public function tests(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->prescriptions->forPatient('test', (string) $request->input('patient_id')));
    }

    /** POST /api/get_medicine_details {prescription_id} */
    public function medicineDetails(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->prescriptions->details('medicine', (string) $request->input('prescription_id')));
    }

    /** POST /api/get_test_details {prescription_id} */
    public function testDetails(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->prescriptions->details('test', (string) $request->input('prescription_id')));
    }

    private function store(string $type, Request $request): JsonResponse
    {
        $request->validate(['patient_id' => 'required']);

        $this->prescriptions->create($type, $request->only(['patient_id', 'date', 'prescription_data']), $request->user());

        return $this->response(true, 'success', 200, '');
    }
}
