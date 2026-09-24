<?php

use App\Http\Controllers\Api\Legacy\AuthController;
use App\Http\Controllers\Api\Legacy\ReferenceDataController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// Legacy API — the old unversioned /api/* routes, kept request- and
// response-compatible for the old mobile app until it moves to /api/v1.
//
// Endpoints are added here module by module as they are ported. Do not
// change a legacy contract; add the improved version under v1 instead.
// =============================================================================

Route::middleware('throttle:10,1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('user_login', [AuthController::class, 'userLogin']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('check-user-mobile', [AuthController::class, 'forgotPasswordDisabled']);
    Route::post('change-forgot-password', [AuthController::class, 'forgotPasswordDisabled']);
});

// Reference data (public in the legacy app)
Route::get('countries', [ReferenceDataController::class, 'countries']);
Route::get('nationalities', [ReferenceDataController::class, 'nationalities']);
Route::get('regions/{country}', [ReferenceDataController::class, 'regions']);
Route::get('cities/{region}', [ReferenceDataController::class, 'cities']);
Route::get('allregions', [ReferenceDataController::class, 'allRegions']);
Route::get('allcities', [ReferenceDataController::class, 'allCities']);

Route::middleware(['jwt.auth', 'tenant'])->group(function () {
    Route::get('logout', [AuthController::class, 'logout']);
});
