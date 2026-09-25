<?php

use App\Http\Controllers\Api\Legacy\AccountController;
use App\Http\Controllers\Api\Legacy\AssessmentController;
use App\Http\Controllers\Api\Legacy\AuthController;
use App\Http\Controllers\Api\Legacy\ClinicalController;
use App\Http\Controllers\Api\Legacy\DocumentController;
use App\Http\Controllers\Api\Legacy\DriverController;
use App\Http\Controllers\Api\Legacy\PatientController;
use App\Http\Controllers\Api\Legacy\PatientLeadController;
use App\Http\Controllers\Api\Legacy\PrescriptionController;
use App\Http\Controllers\Api\Legacy\ReferenceDataController;
use App\Http\Controllers\Api\Legacy\RoleController;
use App\Http\Controllers\Api\Legacy\ScheduleController;
use App\Http\Controllers\Api\Legacy\StaffController;
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

    // Own account
    Route::get('get-user-details', [AccountController::class, 'profile']);
    Route::post('update-firebase-token', [AccountController::class, 'updateFirebaseToken']);
    Route::post('user_feedback', [AccountController::class, 'feedback']);
    Route::post('save-doctor-signature', [AccountController::class, 'saveSignature'])->middleware('permission:prescriptions.write');
    Route::post('video-call/notify', [AccountController::class, 'notifyVideoCall'])->middleware('permission:video.call');
    Route::get('get-all-users', [StaffController::class, 'directory']);

    // Reference data
    Route::get('get_service_packages', [PatientController::class, 'servicePackages']);
    Route::get('get-icd-codes', [PatientController::class, 'icdCodes']);

    // Staff, roles and permissions
    Route::get('users', [StaffController::class, 'index'])->middleware('permission:staff.view');
    Route::post('users', [StaffController::class, 'store'])->middleware('permission:staff.manage');
    Route::get('users/{id}/edit', [StaffController::class, 'edit'])->middleware('permission:staff.view');
    Route::match(['put', 'patch'], 'users/{id}', [StaffController::class, 'update'])->middleware('permission:staff.manage');
    Route::post('delete-staff-member/{id}/{mobile}', [StaffController::class, 'destroy'])->middleware('permission:staff.manage');
    Route::post('addroletouser', [StaffController::class, 'updateRoles'])->middleware('permission:roles.manage');
    Route::get('existingroles/{id}', [StaffController::class, 'roles'])->middleware('permission:roles.manage');
    Route::get('all_users', [StaffController::class, 'exceptDrivers'])->middleware('permission:staff.view,leads.manage');
    Route::get('all_nurses', [StaffController::class, 'visiting'])->middleware('permission:staff.view,schedules.manage');
    Route::post('get-relevant-staff', [StaffController::class, 'withRole'])->middleware('permission:staff.view,schedules.manage');
    Route::get('get_drivers', [DriverController::class, 'index'])->middleware('permission:staff.view,schedules.manage');

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::get('roles/{id}/edit', [RoleController::class, 'edit']);
        Route::match(['put', 'patch'], 'roles/{id}', [RoleController::class, 'update']);
        Route::post('addpermissiontorole', [RoleController::class, 'updatePermissions']);
        Route::get('existingpermissions/{id}', [RoleController::class, 'permissionsOf']);
        Route::get('permissions', [RoleController::class, 'permissions']);
        Route::get('permissions/{id}/edit', [RoleController::class, 'permission']);
        Route::post('permissions', [RoleController::class, 'permissionsReadOnly']);
        Route::match(['put', 'patch'], 'permissions/{id}', [RoleController::class, 'permissionsReadOnly']);
    });

    // Leads
    Route::post('add_patient_lead', [PatientLeadController::class, 'store'])->middleware('permission:leads.manage');
    Route::get('get_patient_leads', [PatientLeadController::class, 'index'])->middleware('permission:leads.view');
    Route::post('get_lead_details', [PatientLeadController::class, 'show'])->middleware('permission:leads.view');
    Route::post('update-patient-lead-details', [PatientLeadController::class, 'update'])->middleware('permission:leads.manage');
    Route::post('add_patient_to_be_assessed', [PatientLeadController::class, 'markToBeAssessed'])->middleware('permission:leads.manage');

    // Assessments and admission
    Route::post('save-assessment-details', [AssessmentController::class, 'store'])->middleware('permission:assessments.manage');
    Route::get('get-all-assessed-patients', [AssessmentController::class, 'index'])->middleware('permission:assessments.view');
    Route::get('get-assessment-details/{id}', [AssessmentController::class, 'show'])->middleware('permission:assessments.view');
    Route::get('get-assessment-invoice/{id}', [AssessmentController::class, 'show'])->middleware('permission:invoices.view,assessments.view');
    Route::post('update-assessment-details', [AssessmentController::class, 'update'])->middleware('permission:assessments.manage');
    Route::post('patients-digital-signature', [AssessmentController::class, 'consent'])->middleware('permission:assessments.manage');
    Route::post('create-homecare-patient', [AssessmentController::class, 'admit'])->middleware('permission:patients.manage');

    // Patients
    Route::post('register_patient', [PatientController::class, 'register'])->middleware('permission:patients.manage');
    Route::post('save_walkin_schedule', [PatientController::class, 'registerWalkIn'])->middleware('permission:patients.walkin');
    Route::get('get-admitted-patients', [PatientController::class, 'index'])->middleware('permission:patients.view');
    Route::get('get-all-confirmed-patients', [PatientController::class, 'admitted'])->middleware('permission:patients.view');
    Route::post('get_patient_data', [PatientController::class, 'show'])->middleware('permission:patients.view');
    Route::get('get-patient-details/{id}', [PatientController::class, 'show'])->middleware('permission:patients.view');
    Route::get('get-admitted-patient-invoice/{id}', [PatientController::class, 'show'])->middleware('permission:invoices.view,patients.view');
    Route::post('update-patient-details', [PatientController::class, 'update'])->middleware('permission:patients.manage');
    Route::post('update_service_package', [PatientController::class, 'updateServices'])->middleware('permission:schedules.manage,attendance.record');
    Route::post('get-patients-from_nurse-duty', [PatientController::class, 'ofStaff'])->middleware('permission:attendance.view');

    // Discharge
    Route::post('add-discharge-details', [PatientController::class, 'scheduleDischarge'])->middleware('permission:discharge.manage');
    Route::post('discharge-patient-by-nurse', [PatientController::class, 'discharge'])->middleware('permission:discharge.manage');
    Route::get('get-all-discharged-patients', [PatientController::class, 'discharged'])->middleware('permission:discharge.view');
    Route::get('get-confirmed-patients-for-discharge-for-nurse', [PatientController::class, 'awaitingDischarge'])->middleware('permission:discharge.view');

    // Duty schedules and attendance
    Route::post('save_staff_duty_schedule_details', [ScheduleController::class, 'store'])->middleware('permission:schedules.manage');
    Route::post('save_staff_alternate_duty_schedule_details', [ScheduleController::class, 'storeSeries'])->middleware('permission:schedules.manage');
    Route::post('update-reschedule-duty-details', [ScheduleController::class, 'reschedule'])->middleware('permission:schedules.manage');
    Route::post('save_short_visit_details', [ScheduleController::class, 'storeShortVisit'])->middleware('permission:schedules.manage');
    Route::post('get_schedule_details', [ScheduleController::class, 'show'])->middleware('permission:schedules.view');
    Route::post('get_staff_schedule_details', [ScheduleController::class, 'showSlot'])->middleware('permission:schedules.view');
    Route::post('get_patients_all_schedules', [ScheduleController::class, 'forPatient'])->middleware('permission:schedules.view');
    Route::post('get_session_details', [ScheduleController::class, 'sessions'])->middleware('permission:schedules.view');
    Route::post('get-all-duty-schedules', [ScheduleController::class, 'since'])->middleware('permission:schedules.view');
    Route::post('scheduled-nurse-list', [ScheduleController::class, 'startingOn'])->middleware('permission:schedules.manage');
    Route::post('get_nurse_schedule', [ScheduleController::class, 'nurseSchedule'])->middleware('permission:schedules.view,attendance.record');
    Route::post('get_staff_schedule', [ScheduleController::class, 'staffSchedule'])->middleware('permission:schedules.view,attendance.record');
    Route::post('update-nurse-signin', [ScheduleController::class, 'signIn'])->middleware('permission:attendance.record');
    Route::post('update-nurse-signout', [ScheduleController::class, 'signOut'])->middleware('permission:attendance.record');
    Route::post('update-staff-signin', [ScheduleController::class, 'signIn'])->middleware('permission:attendance.record');
    Route::post('update-staff-signout', [ScheduleController::class, 'signOut'])->middleware('permission:attendance.record');
    Route::post('update-not-attended-reason', [ScheduleController::class, 'notAttended'])->middleware('permission:schedules.manage,attendance.record');
    Route::post('get-staff-attendance', [ScheduleController::class, 'attendance'])->middleware('permission:attendance.view');
    Route::post('get-medical-staff-attendance/{id}', [ScheduleController::class, 'history'])->middleware('permission:attendance.view');

    // Drivers
    Route::post('save_driver_schedule', [DriverController::class, 'store'])->middleware('permission:schedules.manage');
    Route::post('get_driver_schedule', [DriverController::class, 'mine'])->middleware('permission:schedules.view,attendance.record');
    Route::post('update_driver_pickup', [DriverController::class, 'pickup'])->middleware('permission:attendance.record');
    Route::post('update_driver_drop', [DriverController::class, 'drop'])->middleware('permission:attendance.record');
    Route::post('get-driver-attendance/{id}', [DriverController::class, 'history'])->middleware('permission:attendance.view');

    // Vital signs
    Route::post('store_vitalsigns', [ClinicalController::class, 'storeVitals'])->middleware('permission:vitals.record');
    Route::get('patients/{id}/{time_range}/get-patients-vitalsigns', [ClinicalController::class, 'vitalsInRange'])->middleware('permission:vitals.view');
    Route::get('patients/{id}/{date}/get-patients-vital-signs-bydate', [ClinicalController::class, 'vitalsOnDate'])->middleware('permission:vitals.view');
    Route::get('patients/{id}/get-latest-vitalsigns-data', [ClinicalController::class, 'latestVitals'])->middleware('permission:vitals.view');
    Route::post('patients/update-patients-vitalsigns', [ClinicalController::class, 'updateVitals'])->middleware('permission:vitals.record');

    // Medication
    Route::post('add_medication_schedule', [ClinicalController::class, 'addMedication'])->middleware('permission:medications.manage');
    Route::get('get-patient-medication-schedule/{id}', [ClinicalController::class, 'medicationSchedule'])->middleware('permission:medications.view');
    Route::post('get_administer_medication_list', [ClinicalController::class, 'medicationChecklist'])->middleware('permission:medications.view');
    Route::get('get-administer-medications/{id}/{date}', [ClinicalController::class, 'administeredOn'])->middleware('permission:medications.view');
    Route::post('update-medicine-details', [ClinicalController::class, 'administer'])->middleware('permission:medications.administer');
    Route::post('get_all_guardians', [ClinicalController::class, 'guardians'])->middleware('permission:medications.view');

    // Notes
    Route::post('store_nurse_notes', [ClinicalController::class, 'storeNursingNote'])->middleware('permission:notes.write');
    Route::post('store_staff_notes', [ClinicalController::class, 'storeStaffNote'])->middleware('permission:notes.write');
    Route::post('upload-nursing-note-image-by-nurse', [ClinicalController::class, 'storeNursingImage'])->middleware('permission:notes.write');
    Route::post('upload-image-note-by-nurse', [ClinicalController::class, 'storeStaffImage'])->middleware('permission:notes.write');
    Route::get('patients/{id}/{type}/get-nurse-notes', [ClinicalController::class, 'nursingNotesToday'])->middleware('permission:notes.view');
    Route::get('patients/{id}/{type}/get-staff-notes', [ClinicalController::class, 'staffNotesToday'])->middleware('permission:notes.view');
    Route::get('get-staff-notes-by-date/{id}/{date}/{notes_type}', [ClinicalController::class, 'staffNotesOnDate'])->middleware('permission:notes.view');

    // Documents
    Route::get('patients/{id}/get-patients-documents', [PatientController::class, 'show'])->middleware('permission:documents.view');
    Route::post('upload-patient-document-by-staff', [DocumentController::class, 'uploadDocument'])->middleware('permission:documents.upload');
    Route::post('upload-patient-document-by-doctor', [DocumentController::class, 'uploadDocument'])->middleware('permission:documents.upload');
    Route::post('upload-patient-documents-by-staff', [DocumentController::class, 'uploadDocuments'])->middleware('permission:documents.upload');
    Route::post('upload-emirates-id-by-staff', [DocumentController::class, 'uploadEmiratesId'])->middleware('permission:documents.upload');
    Route::post('upload-specimen-image-by-staff', [DocumentController::class, 'uploadSpecimen'])->middleware('permission:documents.upload');
    Route::post('upload-specimen-images-by-staff', [DocumentController::class, 'uploadSpecimens'])->middleware('permission:documents.upload');
    Route::post('delete-document', [DocumentController::class, 'destroy'])->middleware('permission:documents.delete');
    Route::post('missing-document-notification', [DocumentController::class, 'requestMissing'])->middleware('permission:documents.view');

    // Prescriptions
    Route::post('add-medicine-prescription', [PrescriptionController::class, 'storeMedicine'])->middleware('permission:prescriptions.write');
    Route::post('add-test-prescription', [PrescriptionController::class, 'storeTest'])->middleware('permission:prescriptions.write');
    Route::post('get-medicine-prescriptions', [PrescriptionController::class, 'medicines'])->middleware('permission:prescriptions.view');
    Route::post('get-test-prescriptions', [PrescriptionController::class, 'tests'])->middleware('permission:prescriptions.view');
    Route::post('get_medicine_details', [PrescriptionController::class, 'medicineDetails'])->middleware('permission:prescriptions.view');
    Route::post('get_test_details', [PrescriptionController::class, 'testDetails'])->middleware('permission:prescriptions.view');
});
