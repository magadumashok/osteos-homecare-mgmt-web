<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\AdministerMedicationRequest;
use App\Http\Requests\Clinical\MedicationScheduleRequest;
use App\Http\Requests\Rules;
use App\Services\MedicationService;
use App\Services\PatientService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly MedicationService $medications,
        private readonly PatientService $patients,
    ) {}

    public function index(string $username): JsonResponse
    {
        return $this->response(true, '', 200, $this->medications->scheduleFor($this->patientId($username)));
    }

    public function store(MedicationScheduleRequest $request, string $username): JsonResponse
    {
        $medicine = $this->medications->addSchedule(
            [...$request->validated(), 'patient_id' => $this->patientId($username)],
            $request->user(),
        );

        return $this->response(true, 'Medication scheduled.', 201, $medicine);
    }

    /** The day's medicines with the sessions already given. */
    public function checklist(Request $request, string $username): JsonResponse
    {
        $date = $this->date($request);

        return $this->response(true, '', 200, $this->medications->checklist($this->patientId($username), $date));
    }

    /** What was given on a day, with guardian names and signatures. */
    public function administered(Request $request, string $username): JsonResponse
    {
        $date = $this->date($request);

        return $this->response(true, '', 200, $this->medications->administeredOn($this->patientId($username), $date));
    }

    public function administer(AdministerMedicationRequest $request, string $username): JsonResponse
    {
        $record = $this->medications->recordAdministration(
            [...$request->validated(), 'patient_id' => $this->patientId($username)],
            $request->user(),
        );

        return $this->response(true, 'Medication recorded.', 201, $record);
    }

    /** Delete a scheduled medicine entered wrongly or twice (see MedicationService::deleteSchedule). */
    public function destroy(string $username, string $id): JsonResponse
    {
        $this->medications->deleteSchedule($this->patientId($username), $id);

        return $this->response(true, 'Medicine removed.');
    }

    public function guardians(string $username): JsonResponse
    {
        return $this->response(true, '', 200, $this->medications->guardians($this->patientId($username)));
    }

    private function patientId(string $username): string
    {
        return $this->patients->findOrFail($username)->username;
    }

    private function date(Request $request): string
    {
        return $request->validate(['date' => ['nullable', Rules::DATE]])['date'] ?? now()->format('Y-m-d');
    }
}
