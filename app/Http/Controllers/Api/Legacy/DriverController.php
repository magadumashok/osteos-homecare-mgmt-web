<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Services\DriverScheduleService;
use App\Services\StaffService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Legacy driver endpoints (formerly in MobileUserController and AdmittedPatientsController). */
class DriverController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly DriverScheduleService $trips) {}

    /** GET /api/get_drivers */
    public function index(StaffService $staff): JsonResponse
    {
        return $this->response(true, 'success', 200, ['drivers' => $staff->withRole('transport')->toArray()]);
    }

    /** POST /api/save_driver_schedule */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['driver_id' => 'required', 'staff_id' => 'required', 'date' => 'required']);

        $trip = $this->trips->create($request->only(['driver_id', 'staff_id', 'start_time', 'end_time', 'address', 'date', 'end_date']));

        return $this->response(true, $trip ? 'success' : 'duplicate', 200, '');
    }

    /** POST /api/get_driver_schedule {date} — the signed-in driver's trips. */
    public function mine(Request $request): JsonResponse
    {
        $trips = $this->trips->forDriverOn($request->user()->username, (string) $request->input('date'));

        return $trips->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, ['driverschedule' => $trips]);
    }

    /** POST /api/update_driver_pickup {_id, pickup_time, pickup_latitude, pickup_longitude} */
    public function pickup(Request $request): JsonResponse
    {
        $this->trips->recordPickup($this->trips->findOrFail((string) $request->input('_id')), $request->all());

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/update_driver_drop {_id, drop_time, drop_latitude, drop_longitude} */
    public function drop(Request $request): JsonResponse
    {
        $this->trips->recordDrop($this->trips->findOrFail((string) $request->input('_id')), $request->all());

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/get-driver-attendance/{id} */
    public function history(string $id): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->trips->history($id));
    }
}
