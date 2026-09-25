<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Services\PatientService;
use App\Services\PrescriptionService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `{type}` is "medicine" or "test" (constrained in the routes). */
class PrescriptionController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly PrescriptionService $prescriptions,
        private readonly PatientService $patients,
    ) {}

    public function index(string $username, string $type): JsonResponse
    {
        $patientId = $this->patients->findOrFail($username)->username;

        return $this->response(true, '', 200, $this->prescriptions->forPatient($type, $patientId));
    }

    public function store(Request $request, string $username, string $type): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', Rules::DATE],
            'prescription_data' => ['required', 'array', 'min:1'],
        ]);

        $prescription = $this->prescriptions->create(
            $type,
            [...$data, 'patient_id' => $this->patients->findOrFail($username)->username],
            $request->user(),
        );

        return $this->response(true, 'Prescription saved.', 201, $prescription);
    }

    public function show(string $type, string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->prescriptions->details($type, $id));
    }
}
