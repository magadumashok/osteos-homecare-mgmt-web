<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Exceptions\ScheduleConflictException;
use App\Http\Controllers\Controller;
use App\Models\StaffDutySchedule;
use App\Services\DutyScheduleService;
use App\Services\ScheduleConflictService;
use App\Services\ShortVisitService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy duty schedule and attendance endpoints (formerly in
 * MobileUserController and AdmittedPatientsController).
 */
class ScheduleController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly DutyScheduleService $duties) {}

    /**
     * POST /api/save_staff_duty_schedule_details
     * As before, overlapping duties are not rejected here. "duplicate"
     * means the duty was saved but the driver already had the trip.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['staff_id' => 'required', 'patient_id' => 'required', 'date' => 'required', 'start_time' => 'required', 'end_time' => 'required']);

        $result = $this->duties->create($this->scheduleFields($request), checkConflicts: false);

        return $this->response(true, $result['driver_duplicate'] ? 'duplicate' : 'success', 200, '');
    }

    /** POST /api/save_staff_alternate_duty_schedule_details {dateTimeArray: [{start_date, end_date}], ...} */
    public function storeSeries(Request $request): JsonResponse
    {
        $request->validate(['staff_id' => 'required', 'dateTimeArray' => 'required|array', 'start_time' => 'required', 'end_time' => 'required']);

        try {
            $this->duties->createSeries($this->scheduleFields($request), $request->input('dateTimeArray'));
        } catch (ScheduleConflictException) {
            return $this->response(true, 'nurse assigned already', 200, '');
        }

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/update-reschedule-duty-details {_id, ...} */
    public function reschedule(Request $request): JsonResponse
    {
        try {
            $this->duties->reschedule($this->duties->findOrFail((string) $request->input('_id')), $this->scheduleFields($request));
        } catch (ScheduleConflictException $e) {
            return $this->response(true, $e->conflict === ScheduleConflictService::PATIENT
                ? 'patient is already assigned to other nurse for this time'
                : 'staff assigned already', 200, '');
        }

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/get_schedule_details {schedule_id} */
    public function show(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, StaffDutySchedule::where('_id', (string) $request->input('schedule_id'))->first());
    }

    /** POST /api/get_staff_schedule_details — find a duty by its exact slot. */
    public function showSlot(Request $request): JsonResponse
    {
        $slot = $request->only(['patient_id', 'staff_id', 'date', 'start_time', 'end_date', 'end_time']);

        return $this->response(true, 'success', 200, $this->duties->findSlot($slot));
    }

    /** POST /api/get_patients_all_schedules {id} */
    public function forPatient(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->duties->forPatient((string) $request->input('id')));
    }

    /** POST /api/get_session_details {patient_id, duty_role} */
    public function sessions(Request $request): JsonResponse
    {
        $sessions = $this->duties->sessions((string) $request->input('patient_id'), $request->input('duty_role'));

        return $this->response(true, 'success', 200, ['scheduled_sessions' => $sessions]);
    }

    /** POST /api/get_nurse_schedule {date} — the signed-in nurse's day. */
    public function nurseSchedule(Request $request): JsonResponse
    {
        return $this->mySchedule($request, 'nurseschedule');
    }

    /** POST /api/get_staff_schedule {date} — the signed-in doctor's or physiotherapist's day. */
    public function staffSchedule(Request $request): JsonResponse
    {
        return $this->mySchedule($request, 'staffschedule');
    }

    /** POST /api/update-nurse-signin and /api/update-staff-signin {_id, signin_time, signin_latitude, signin_longitude} */
    public function signIn(Request $request): JsonResponse
    {
        $this->duties->signIn($this->duties->findOrFail((string) $request->input('_id')), $request->all());

        return $this->response(true, $request->is('api/update-staff-signin') ? 'signin_success' : 'success', 200, '');
    }

    /** POST /api/update-nurse-signout and /api/update-staff-signout */
    public function signOut(Request $request): JsonResponse
    {
        $this->duties->signOut($this->duties->findOrFail((string) $request->input('_id')), $request->all());

        return $this->response(true, $request->is('api/update-staff-signout') ? 'signout_success' : 'success', 200, '');
    }

    /** POST /api/update-not-attended-reason {_id, not_attended_reason} */
    public function notAttended(Request $request): JsonResponse
    {
        $this->duties->markNotAttended($this->duties->findOrFail((string) $request->input('_id')), $request->input('not_attended_reason'));

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/get-all-duty-schedules {date} — duties from that date on. */
    public function since(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->duties->since((string) $request->input('date')));
    }

    /** POST /api/scheduled-nurse-list {date} — duties starting that day, for booking drivers. */
    public function startingOn(Request $request): JsonResponse
    {
        $duties = $this->duties->startingOn((string) $request->input('date'));

        return $duties->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, ['schedulednurses' => $duties]);
    }

    /** POST /api/get-staff-attendance {staff_id, patient_id} */
    public function attendance(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->duties->attendance((string) $request->input('staff_id'), (string) $request->input('patient_id')));
    }

    /** POST /api/get-medical-staff-attendance/{id} */
    public function history(string $id): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->duties->history($id));
    }

    /** POST /api/save_short_visit_details */
    public function storeShortVisit(Request $request, ShortVisitService $visits): JsonResponse
    {
        $request->validate(['staff_id' => 'required', 'patient_name' => 'required']);

        $visits->create($request->all());

        return $this->response(true, 'success', 200, '');
    }

    private function mySchedule(Request $request, string $key): JsonResponse
    {
        $duties = $this->duties->forStaffOn($request->user()->username, (string) $request->input('date'));

        return $duties->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, [$key => $duties]);
    }

    private function scheduleFields(Request $request): array
    {
        return array_filter($request->only(StaffDutySchedule::SCHEDULE_FIELDS), fn ($value) => $value !== null);
    }
}
