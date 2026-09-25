<?php

namespace App\Exceptions;

use App\Services\ScheduleConflictService;
use Illuminate\Http\JsonResponse;

/**
 * A duty would double-book a patient or a staff member. v1 renders it as
 * 409; legacy controllers catch it and answer with the old messages.
 */
class ScheduleConflictException extends \RuntimeException
{
    /** @param  ScheduleConflictService::PATIENT|ScheduleConflictService::STAFF  $conflict */
    public function __construct(public readonly string $conflict)
    {
        parent::__construct(ScheduleConflictService::message($conflict));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'data' => ['conflict' => $this->conflict],
        ], 409);
    }
}
