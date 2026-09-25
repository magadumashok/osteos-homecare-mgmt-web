<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedules\ShortVisitRequest;
use App\Services\ShortVisitService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;

class ShortVisitController extends Controller
{
    use ResponseTrait;

    public function store(ShortVisitRequest $request, ShortVisitService $visits): JsonResponse
    {
        return $this->response(true, 'Short visit scheduled.', 201, $visits->create($request->validated()));
    }
}
