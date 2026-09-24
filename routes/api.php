<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// API v1 — used by the new web app. All routes are prefixed with /api/v1.
//
// Protected routes run: jwt.auth → tenant (switch to the user's homecare
// database) → permission:<name> (checked on the server, not just the UI).
// =============================================================================

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Public
    // -------------------------------------------------------------------------
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/register', [AuthController::class, 'register']);
    });

    // Accepts a recently expired token (within JWT_REFRESH_TTL).
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // -------------------------------------------------------------------------
    // Authenticated
    // -------------------------------------------------------------------------
    Route::middleware(['jwt.auth', 'tenant'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });
});
