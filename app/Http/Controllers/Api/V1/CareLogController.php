<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Models\CareLog;
use App\Models\StaffDutySchedule;
use App\Services\CareLogService;
use App\Services\DutyScheduleService;
use App\Services\PermissionService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Caretakers' daily log of 24-hour duties, skipped days and covers. */
class CareLogController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly CareLogService $logs,
        private readonly DutyScheduleService $duties,
        private readonly PermissionService $permissions,
    ) {}

    /** ?date=Y-m-d (default today) [&patient_id=]: every 24-hour duty running that day with its update. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['date' => ['nullable', Rules::DATE], 'patient_id' => ['nullable', 'string']]);

        return $this->response(true, '', 200, $this->logs->day($filters['date'] ?? now()->format('Y-m-d'), $filters['patient_id'] ?? null));
    }

    /** Care tasks offered when scheduling a caretaker. */
    public function tasks(): JsonResponse
    {
        return $this->response(true, '', 200, CareLog::DEFAULT_TASKS);
    }

    /** The duty (with names) and every logged or skipped day of it. */
    public function forDuty(Request $request, string $id): JsonResponse
    {
        $duty = $this->duty($request, $id, 'schedules.view');

        return $this->response(true, '', 200, [
            'duty' => $this->duties->decorate(collect([$duty]))->first(),
            'logs' => $this->logs->forDuty($duty),
        ]);
    }

    public function save(Request $request, string $id, string $date): JsonResponse
    {
        validator(['date' => $date], ['date' => ['required', Rules::DATE]])->validate();
        $data = $request->validate([
            'tasks' => ['nullable', 'array', 'max:40'],
            'tasks.*.name' => ['required', 'string', 'max:120'],
            'tasks.*.done' => ['nullable', 'boolean'],
            'tasks.*.time' => ['nullable', Rules::TIME],
            'tasks.*.note' => ['nullable', 'string', 'max:500'],
            'symptoms' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $log = $this->logs->save($this->duty($request, $id, 'schedules.manage'), $date, $data, $request->user());

        return $this->response(true, 'Care update saved.', 200, $log);
    }

    public function skip(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', Rules::DATE],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $log = $this->logs->skip($this->duty($request, $id, 'schedules.manage'), $data['date'], $data['reason'], $request->user());

        return $this->response(true, 'Day skipped. Your coordinator has been told.', 200, $log);
    }

    public function cover(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', Rules::DATE],
            'staff_id' => ['required', 'string'],
        ]);

        $cover = $this->logs->cover($this->duties->findOrFail($id), $data['date'], $data['staff_id']);

        return $this->response(true, 'Cover caretaker assigned.', 201, $cover);
    }

    /** The caretaker on the duty, or anyone holding $permission. */
    private function duty(Request $request, string $id, string $permission): StaffDutySchedule
    {
        $duty = $this->duties->findOrFail($id);
        $user = $request->user();

        abort_unless(
            $duty->staff_id === $user->username || $this->permissions->allows($user, $permission),
            403,
            'This duty is assigned to someone else.',
        );

        return $duty;
    }
}
