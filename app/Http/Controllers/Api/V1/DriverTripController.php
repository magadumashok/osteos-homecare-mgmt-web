<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules;
use App\Http\Requests\Schedules\DriverTripRequest;
use App\Models\DriverSchedule;
use App\Services\DriverScheduleService;
use App\Services\PermissionService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverTripController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly DriverScheduleService $trips,
        private readonly PermissionService $permissions,
    ) {}

    /** The signed-in driver's trips for a day (defaults to today). */
    public function mine(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['nullable', Rules::DATE]])['date'] ?? now()->format('Y-m-d');

        return $this->response(true, '', 200, $this->trips->forDriverOn($request->user()->username, $date));
    }

    public function store(DriverTripRequest $request): JsonResponse
    {
        $trip = $this->trips->create($request->validated());

        return $trip
            ? $this->response(true, 'Trip scheduled.', 201, $trip)
            : $this->response(false, 'This driver already has this trip.', 409);
    }

    public function history(string $driverId): JsonResponse
    {
        return $this->response(true, '', 200, $this->trips->history($driverId));
    }

    public function pickup(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'pickup_time' => ['required', Rules::TIME],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->trips->recordPickup($this->ownTrip($request, $id), $data);

        return $this->response(true, 'Pickup recorded.');
    }

    public function drop(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'drop_time' => ['required', Rules::TIME],
            'drop_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'drop_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->trips->recordDrop($this->ownTrip($request, $id), $data);

        return $this->response(true, 'Drop recorded.');
    }

    private function ownTrip(Request $request, string $id): DriverSchedule
    {
        $trip = $this->trips->findOrFail($id);
        $user = $request->user();

        abort_unless(
            $trip->driver_id === $user->username || $this->permissions->allows($user, 'schedules.manage'),
            403,
            'This trip is assigned to another driver.',
        );

        return $trip;
    }
}
