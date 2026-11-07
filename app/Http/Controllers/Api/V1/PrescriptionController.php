<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Services\PatientAccessService;
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
        private readonly PatientAccessService $access,
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

    public function show(Request $request, string $type, string $id): JsonResponse
    {
        $prescription = $this->prescriptions->details($type, $id);

        abort_unless($this->access->canSee($request->user(), $prescription->patient_id), 403, 'This patient is not assigned to you.');

        return $this->response(true, '', 200, $prescription);
    }
}
