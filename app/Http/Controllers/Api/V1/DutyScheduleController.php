<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Http\Requests\Schedules\DutyRequest;
use App\Http\Requests\Schedules\DutySeriesRequest;
use App\Models\StaffDutySchedule;
use App\Services\DutyScheduleService;
use App\Services\PermissionService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DutyScheduleController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly DutyScheduleService $duties,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * ?date=Y-m-d   duties starting that day, plus 24-hour duties running through it (with staff and patient names)
     * ?since=Y-m-d  duties starting that day or later, newest first
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['date' => ['nullable', Rules::DATE], 'since' => ['nullable', Rules::DATE]]);

        $duties = isset($filters['date'])
            ? $this->duties->onDay($filters['date'])
            : $this->duties->since($filters['since'] ?? now()->format('Y-m-d'));

        return $this->response(true, '', 200, $duties);
    }

    /** The signed-in staff member's duties for a day (defaults to today). */
    public function mine(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['nullable', Rules::DATE]])['date'] ?? now()->format('Y-m-d');

        return $this->response(true, '', 200, $this->duties->forStaffOn($request->user()->username, $date));
    }

    public function store(DutyRequest $request): JsonResponse
    {
        $result = $this->duties->create($request->validated());

        $message = $result['driver_duplicate'] ? 'Duty scheduled. The driver already had this trip.' : 'Duty scheduled.';

        return $this->response(true, $message, 201, $result);
    }

    public function storeSeries(DutySeriesRequest $request): JsonResponse
    {
        $data = $request->validated();
        $duties = $this->duties->createSeries($data, $data['ranges']);

        return $this->response(true, 'Duties scheduled.', 201, $duties);
    }

    public function show(string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->duties->findOrFail($id));
    }

    public function update(DutyRequest $request, string $id): JsonResponse
    {
        $duty = $this->duties->reschedule($this->duties->findOrFail($id), $request->validated());

        return $this->response(true, 'Duty rescheduled.', 200, $duty);
    }

    public function signIn(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signin_time' => ['required', Rules::TIME],
            'signin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'signin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->duties->signIn($this->ownDuty($request, $id), $data);

        return $this->response(true, 'Signed in.');
    }

    public function signOut(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signout_time' => ['required', Rules::TIME],
            'signout_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'signout_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->duties->signOut($this->ownDuty($request, $id), $data);

        return $this->response(true, 'Signed out.');
    }

    public function notAttended(Request $request, string $id): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];

        $this->duties->markNotAttended($this->ownDuty($request, $id), $reason);

        return $this->response(true, 'Duty marked as not attended.');
    }

    /** Duties of one staff member, with one patient (?patient_id=) or all, newest first. */
    public function attendance(Request $request, string $staffId): JsonResponse
    {
        $patientId = $request->query('patient_id');

        return $this->response(true, '', 200, $patientId
            ? $this->duties->attendance($staffId, (string) $patientId)
            : $this->duties->history($staffId));
    }

    /** Staff sign their own duties; schedulers may act on anyone's. */
    private function ownDuty(Request $request, string $id): StaffDutySchedule
    {
        $duty = $this->duties->findOrFail($id);
        $user = $request->user();

        abort_unless(
            $duty->staff_id === $user->username || $this->permissions->allows($user, 'schedules.manage'),
            403,
            'This duty is assigned to someone else.',
        );

        return $duty;
    }
}
