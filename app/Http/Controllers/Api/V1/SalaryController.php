<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Models\User;
use App\Services\DutyScheduleService;
use App\Services\SalaryService;
use App\Services\StaffService;
use App\Traits\ResponseTrait;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Days worked and salary for a date range (default: this month). */
class SalaryController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly SalaryService $salary,
        private readonly DutyScheduleService $duties,
    ) {}

    /** The signed-in staff member's own salary. */
    public function mine(Request $request): JsonResponse
    {
        return $this->earnings($request, $request->user());
    }

    /** A staff member's salary, for admins. */
    public function forStaff(Request $request, string $id, StaffService $staff): JsonResponse
    {
        return $this->earnings($request, $staff->findOrFail($id));
    }

    private function earnings(Request $request, User $user): JsonResponse
    {
        $range = $request->validate([
            'from' => ['nullable', Rules::DATE],
            'to' => ['nullable', Rules::DATE],
        ]);

        $today = CarbonImmutable::parse($this->duties->today());
        $from = CarbonImmutable::parse($range['from'] ?? $today->startOfMonth()->format('Y-m-d'));
        $to = CarbonImmutable::parse($range['to'] ?? $today->endOfMonth()->format('Y-m-d'));

        if ($to->lt($from)) {
            return $this->response(false, 'The end date must be on or after the start date.', 422);
        }
        if ($from->diffInDays($to) > 366) {
            return $this->response(false, 'Choose a period of one year or less.', 422);
        }

        return $this->response(true, '', 200, $this->salary->earnings($user, $from->format('Y-m-d'), $to->format('Y-m-d')));
    }
}
