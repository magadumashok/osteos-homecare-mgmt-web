<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\VitalSignRequest;
use App\Http\Requests\Rules;
use App\Services\PatientService;
use App\Services\VitalSignService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VitalSignController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly VitalSignService $vitals,
        private readonly PatientService $patients,
    ) {}

    /** ?date=Y-m-d for one day, else ?range=all_time|today|this_week|this_month (default all_time). */
    public function index(Request $request, string $username): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', Rules::DATE],
            'range' => ['nullable', Rule::in(VitalSignService::RANGES)],
        ]);
        $patientId = $this->patients->findOrFail($username)->username;

        $readings = isset($filters['date'])
            ? $this->vitals->onDate($patientId, $filters['date'])
            : $this->vitals->inRange($patientId, $filters['range'] ?? 'all_time');

        return $this->response(true, '', 200, $readings);
    }

    public function latest(string $username): JsonResponse
    {
        return $this->response(true, '', 200, $this->vitals->latest($this->patients->findOrFail($username)->username));
    }

    public function store(VitalSignRequest $request, string $username): JsonResponse
    {
        $user = $request->user();

        $reading = $this->vitals->store([
            ...$request->validated(),
            'patient_id' => $this->patients->findOrFail($username)->username,
            'nurse_id' => $user->username,
            'nurse_name' => $user->name,
        ]);

        return $this->response(true, 'Vital signs recorded.', 201, $reading);
    }

    public function update(VitalSignRequest $request, string $id): JsonResponse
    {
        $reading = $this->vitals->update($this->vitals->findOrFail($id), $request->validated());

        return $this->response(true, 'Vital signs updated.', 200, $reading);
    }
}
