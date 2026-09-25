<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\PatientRequest;
use App\Http\Requests\Patients\WalkInRequest;
use App\Http\Requests\Rules;
use App\Services\DutyScheduleService;
use App\Services\PatientService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly PatientService $patients) {}

    /** ?status=all includes discharged patients. */
    public function index(Request $request): JsonResponse
    {
        $patients = $request->query('status') === 'all' ? $this->patients->all() : $this->patients->admitted();

        return $this->response(true, '', 200, $patients);
    }

    public function store(PatientRequest $request): JsonResponse
    {
        return $this->response(true, 'Patient registered.', 201, $this->patients->register($request->validated()));
    }

    public function storeWalkIn(WalkInRequest $request): JsonResponse
    {
        [$patient, $duty] = $this->patients->registerWalkIn($request->user(), ...$request->parts());

        return $this->response(true, 'Walk-in patient registered.', 201, ['patient' => $patient, 'duty' => $duty]);
    }

    public function show(string $username): JsonResponse
    {
        return $this->response(true, '', 200, $this->patients->findOrFail($username));
    }

    public function update(PatientRequest $request, string $username): JsonResponse
    {
        $patient = $this->patients->update($this->patients->findOrFail($username), $request->validated());

        return $this->response(true, 'Patient updated.', 200, $patient);
    }

    /** Services delivered, recorded against a duty (optional) and the patient. */
    public function updateServices(Request $request, string $username, DutyScheduleService $duties): JsonResponse
    {
        $data = $request->validate([
            'services' => ['required', 'array'],
            'services.*.service_package' => ['required', 'string', 'max:120'],
            'services.*.fees' => ['nullable', 'numeric', 'min:0'],
            'schedule_id' => ['nullable', 'string'],
        ]);

        $duty = isset($data['schedule_id']) ? $duties->findOrFail($data['schedule_id']) : null;
        $this->patients->updateServices($this->patients->findOrFail($username), $duty, $data['services']);

        return $this->response(true, 'Services updated.');
    }

    /** Terms, consent, payment flags and the patient's signature (base64 PNG). */
    public function consent(Request $request, string $username): JsonResponse
    {
        $data = $request->validate([
            'terms_conditions' => ['required', 'boolean'],
            'consent_form' => ['required', 'boolean'],
            'amount_paid' => ['required', 'boolean'],
            'image' => ['nullable', 'string'],
        ]);

        $patient = $this->patients->findOrFail($username);
        $this->patients->recordConsent($patient, $data, $data['image'] ?? null);

        return $this->response(true, 'Consent recorded.', 200, $patient->fresh());
    }

    public function discharged(): JsonResponse
    {
        return $this->response(true, '', 200, $this->patients->discharged());
    }

    /** Patients of the signed-in staff member whose discharge date is set. */
    public function awaitingDischarge(Request $request): JsonResponse
    {
        return $this->response(true, '', 200, $this->patients->awaitingDischargeFor($request->user()));
    }

    public function scheduleDischarge(Request $request, string $username): JsonResponse
    {
        $data = $request->validate(['date' => ['required', Rules::DATE], 'time' => ['nullable', Rules::TIME]]);

        $this->patients->scheduleDischarge($this->patients->findOrFail($username), $data['date'], $data['time'] ?? null);

        return $this->response(true, 'Discharge date set.');
    }

    public function discharge(Request $request, string $username): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', Rules::DATE],
            'time' => ['nullable', Rules::TIME],
            'summary' => ['required', 'string', 'max:10000'],
        ]);

        $record = $this->patients->discharge($this->patients->findOrFail($username), $request->user(), $data);

        return $this->response(true, 'Patient discharged.', 201, $record);
    }

    public function schedules(string $username, DutyScheduleService $duties): JsonResponse
    {
        return $this->response(true, '', 200, $duties->forPatient($this->patients->findOrFail($username)->username));
    }
}
