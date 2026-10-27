<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CareLogController;
use App\Http\Controllers\Api\V1\DriverTripController;
use App\Http\Controllers\Api\V1\DutyScheduleController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientDocumentController;
use App\Http\Controllers\Api\V1\PatientLeadController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\ShortVisitController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\VitalSignController;
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

        // One-time codes (OTP login and forgot password)
        Route::post('auth/otp/send', [AuthController::class, 'sendOtp']);
        Route::post('auth/otp/login', [AuthController::class, 'otpLogin']);
        Route::post('auth/password/reset', [AuthController::class, 'resetPassword']);
    });

    // Reference data (needed on the registration screen)
    Route::get('reference/countries', [ReferenceDataController::class, 'countries']);
    Route::get('reference/nationalities', [ReferenceDataController::class, 'nationalities']);
    Route::get('reference/regions/{country}', [ReferenceDataController::class, 'regions']);
    Route::get('reference/cities/{region}', [ReferenceDataController::class, 'cities']);

    // Accepts a recently expired token (within JWT_REFRESH_TTL).
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // -------------------------------------------------------------------------
    // Authenticated
    // -------------------------------------------------------------------------
    Route::middleware(['jwt.auth', 'tenant'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Own account: no permission needed beyond being signed in.
        Route::put('account/firebase-token', [AccountController::class, 'updateFirebaseToken']);
        Route::post('account/feedback', [AccountController::class, 'feedback']);
        Route::put('account/password', [AuthController::class, 'changePassword']);
        Route::post('account/profile-image', [AccountController::class, 'saveProfileImage']);
        Route::post('account/signature', [AccountController::class, 'saveSignature'])->middleware('permission:prescriptions.write');
        Route::post('video-calls', [AccountController::class, 'videoCall'])->middleware('permission:video.call');
        Route::get('staff/directory', [StaffController::class, 'directory']);

        Route::get('reference/service-packages', [ReferenceDataController::class, 'servicePackages']);
        Route::get('reference/icd-codes', [ReferenceDataController::class, 'icdCodes']);

        // Leads
        Route::get('leads', [PatientLeadController::class, 'index'])->middleware('permission:leads.view');
        Route::post('leads', [PatientLeadController::class, 'store'])->middleware('permission:leads.manage');
        Route::get('leads/{lead}', [PatientLeadController::class, 'show'])->middleware('permission:leads.view');
        Route::put('leads/{lead}', [PatientLeadController::class, 'update'])->middleware('permission:leads.manage');
        Route::post('leads/{lead}/to-be-assessed', [PatientLeadController::class, 'markToBeAssessed'])->middleware('permission:leads.manage');

        // Initial assessments
        Route::get('assessments', [AssessmentController::class, 'index'])->middleware('permission:assessments.view');
        Route::post('assessments', [AssessmentController::class, 'store'])->middleware('permission:assessments.manage');
        Route::get('assessments/{id}', [AssessmentController::class, 'show'])->middleware('permission:assessments.view,invoices.view');
        Route::put('assessments/{id}', [AssessmentController::class, 'update'])->middleware('permission:assessments.manage');
        Route::post('assessments/{id}/admit', [AssessmentController::class, 'admit'])->middleware('permission:patients.manage');

        // Patients
        Route::get('patients', [PatientController::class, 'index'])->middleware('permission:patients.view');
        Route::post('patients', [PatientController::class, 'store'])->middleware('permission:patients.manage');
        Route::post('patients/walk-in', [PatientController::class, 'storeWalkIn'])->middleware('permission:patients.walkin');
        Route::get('patients/discharged', [PatientController::class, 'discharged'])->middleware('permission:discharge.view');
        Route::get('patients/awaiting-discharge', [PatientController::class, 'awaitingDischarge'])->middleware('permission:discharge.view');

        Route::prefix('patients/{username}')->group(function () {
            Route::get('/', [PatientController::class, 'show'])->middleware('permission:patients.view,invoices.view');
            Route::put('/', [PatientController::class, 'update'])->middleware('permission:patients.manage');
            Route::put('services', [PatientController::class, 'updateServices'])->middleware('permission:schedules.manage,attendance.record');
            Route::post('consent', [PatientController::class, 'consent'])->middleware('permission:assessments.manage');
            Route::put('discharge-date', [PatientController::class, 'scheduleDischarge'])->middleware('permission:discharge.manage');
            Route::post('discharge', [PatientController::class, 'discharge'])->middleware('permission:discharge.manage');
            Route::get('schedules', [PatientController::class, 'schedules'])->middleware('permission:schedules.view');

            Route::get('documents', [PatientDocumentController::class, 'index'])->middleware('permission:documents.view');
            Route::post('documents', [PatientDocumentController::class, 'store'])->middleware('permission:documents.upload');
            Route::delete('documents', [PatientDocumentController::class, 'destroy'])->middleware('permission:documents.delete');
            Route::post('specimens', [PatientDocumentController::class, 'storeSpecimens'])->middleware('permission:documents.upload');
            Route::post('missing-documents', [PatientDocumentController::class, 'requestMissing'])->middleware('permission:documents.view');

            Route::get('vitals', [VitalSignController::class, 'index'])->middleware('permission:vitals.view');
            Route::get('vitals/latest', [VitalSignController::class, 'latest'])->middleware('permission:vitals.view');
            Route::post('vitals', [VitalSignController::class, 'store'])->middleware('permission:vitals.record');

            Route::get('notes/{kind}', [NoteController::class, 'index'])->whereIn('kind', ['nursing', 'staff'])->middleware('permission:notes.view');
            Route::post('notes/{kind}', [NoteController::class, 'store'])->whereIn('kind', ['nursing', 'staff'])->middleware('permission:notes.write');

            Route::get('medications', [MedicationController::class, 'index'])->middleware('permission:medications.view');
            Route::post('medications', [MedicationController::class, 'store'])->middleware('permission:medications.manage');
            Route::get('medications/checklist', [MedicationController::class, 'checklist'])->middleware('permission:medications.view');
            Route::get('medications/administered', [MedicationController::class, 'administered'])->middleware('permission:medications.view');
            Route::post('medications/administered', [MedicationController::class, 'administer'])->middleware('permission:medications.administer');
            Route::delete('medications/{id}', [MedicationController::class, 'destroy'])->middleware('permission:medications.manage,medications.administer');
            Route::get('guardians', [MedicationController::class, 'guardians'])->middleware('permission:medications.view');

            Route::get('prescriptions/{type}', [PrescriptionController::class, 'index'])->whereIn('type', ['medicine', 'test'])->middleware('permission:prescriptions.view');
            Route::post('prescriptions/{type}', [PrescriptionController::class, 'store'])->whereIn('type', ['medicine', 'test'])->middleware('permission:prescriptions.write');
        });

        Route::put('vitals/{id}', [VitalSignController::class, 'update'])->middleware('permission:vitals.record');
        Route::get('prescriptions/{type}/{id}', [PrescriptionController::class, 'show'])->whereIn('type', ['medicine', 'test'])->middleware('permission:prescriptions.view');

        // Duty schedules and attendance
        Route::get('duties', [DutyScheduleController::class, 'index'])->middleware('permission:schedules.view');
        Route::get('duties/mine', [DutyScheduleController::class, 'mine'])->middleware('permission:schedules.view,attendance.record');
        Route::post('duties', [DutyScheduleController::class, 'store'])->middleware('permission:schedules.manage');
        Route::post('duties/series', [DutyScheduleController::class, 'storeSeries'])->middleware('permission:schedules.manage');
        Route::get('duties/{id}', [DutyScheduleController::class, 'show'])->middleware('permission:schedules.view');
        Route::put('duties/{id}', [DutyScheduleController::class, 'update'])->middleware('permission:schedules.manage');
        Route::post('duties/{id}/sign-in', [DutyScheduleController::class, 'signIn'])->middleware('permission:attendance.record');
        Route::post('duties/{id}/sign-out', [DutyScheduleController::class, 'signOut'])->middleware('permission:attendance.record');
        Route::post('duties/{id}/not-attended', [DutyScheduleController::class, 'notAttended'])->middleware('permission:schedules.manage,attendance.record');
        Route::post('short-visits', [ShortVisitController::class, 'store'])->middleware('permission:schedules.manage');

        // 24-hour caretaker duties: daily care log, skipped days and covers.
        Route::get('care-logs', [CareLogController::class, 'index'])->middleware('permission:schedules.view');
        Route::get('care-logs/tasks', [CareLogController::class, 'tasks'])->middleware('permission:schedules.view,attendance.record');
        Route::get('duties/{id}/care-logs', [CareLogController::class, 'forDuty'])->middleware('permission:schedules.view,attendance.record');
        Route::put('duties/{id}/care-logs/{date}', [CareLogController::class, 'save'])->middleware('permission:schedules.manage,attendance.record');
        Route::post('duties/{id}/skip-day', [CareLogController::class, 'skip'])->middleware('permission:schedules.manage,attendance.record');
        Route::post('duties/{id}/cover-day', [CareLogController::class, 'cover'])->middleware('permission:schedules.manage');

        Route::get('driver-trips/mine', [DriverTripController::class, 'mine'])->middleware('permission:schedules.view,attendance.record');
        Route::post('driver-trips', [DriverTripController::class, 'store'])->middleware('permission:schedules.manage');
        Route::post('driver-trips/{id}/pickup', [DriverTripController::class, 'pickup'])->middleware('permission:attendance.record');
        Route::post('driver-trips/{id}/drop', [DriverTripController::class, 'drop'])->middleware('permission:attendance.record');

        Route::get('attendance/staff/{staffId}', [DutyScheduleController::class, 'attendance'])->middleware('permission:attendance.view');
        Route::get('attendance/drivers/{driverId}', [DriverTripController::class, 'history'])->middleware('permission:attendance.view');

        // Staff and roles
        Route::get('staff', [StaffController::class, 'index'])->middleware('permission:staff.view,schedules.manage,leads.manage');
        Route::post('staff', [StaffController::class, 'store'])->middleware('permission:staff.manage');
        Route::get('staff/{id}', [StaffController::class, 'show'])->middleware('permission:staff.view');
        Route::put('staff/{id}', [StaffController::class, 'update'])->middleware('permission:staff.manage');
        Route::delete('staff/{id}', [StaffController::class, 'destroy'])->middleware('permission:staff.manage');
        Route::put('staff/{id}/roles', [StaffController::class, 'updateRoles'])->middleware('permission:roles.manage');
        Route::get('staff/{id}/patients', [StaffController::class, 'patients'])->middleware('permission:attendance.view');

        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{id}', [RoleController::class, 'update']);
            Route::put('roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
            Route::delete('roles/{id}', [RoleController::class, 'destroy']);
        });
    });
});
