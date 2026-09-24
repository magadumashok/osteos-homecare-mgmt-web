<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * The {success, message, data} envelope used by the legacy app and the EMR.
 */
trait ResponseTrait
{
    public function response(bool $success = false, string $message = '', int $status = 200, mixed $data = []): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
