<?php

namespace App\Services;

use App\Models\DriverSchedule;
use Illuminate\Support\Collection;

class DriverScheduleService
{
    private const SLOT_FIELDS = ['driver_id', 'staff_id', 'date', 'end_date', 'start_time', 'end_time'];

    public function __construct(private readonly UserDirectoryService $users) {}

    public function findOrFail(string $id): DriverSchedule
    {
        return DriverSchedule::where('_id', $id)->firstOrFail();
    }

    /** Create the schedule, or return null if this driver already has this exact trip. */
    public function create(array $data): ?DriverSchedule
    {
        $slot = array_intersect_key($data, array_flip(self::SLOT_FIELDS));

        if (DriverSchedule::where($slot)->exists()) {
            return null;
        }

        return DriverSchedule::create([
            ...$data,
            'pickup' => false, 'pickup_time' => '',
            'drop' => false, 'drop_time' => '',
        ]);
    }

    /** A driver's trips on a date, with the staff member's name and role. */
    public function forDriverOn(string $driverId, string $date): Collection
    {
        $trips = DriverSchedule::where('driver_id', $driverId)->where('date', $date)->orderBy('start_time')->get();
        $staff = $this->users->byUsername($trips->pluck('staff_id'));

        return $trips->each(function (DriverSchedule $trip) use ($staff) {
            $trip->staff_name = $staff->get($trip->staff_id)?->displayName();
            $trip->staff_role = $staff->get($trip->staff_id)?->primaryRole();
        });
    }

    /** Every trip of a driver, newest first, with driver and staff names. */
    public function history(string $driverId): Collection
    {
        $trips = DriverSchedule::where('driver_id', $driverId)->orderBy('date', 'desc')->get();
        $people = $this->users->byUsername([$driverId, ...$trips->pluck('staff_id')]);

        return $trips->each(function (DriverSchedule $trip) use ($people) {
            $trip->driver_name = $people->get($trip->driver_id)?->displayName();
            $trip->staff_name = $people->get($trip->staff_id)?->displayName();
            $trip->staff_role = $people->get($trip->staff_id)?->primaryRole();
        });
    }

    public function recordPickup(DriverSchedule $trip, array $data): void
    {
        $trip->update([
            'pickup' => true,
            'pickup_time' => $data['pickup_time'] ?? null,
            'pickup_latitude' => $data['pickup_latitude'] ?? null,
            'pickup_longitude' => $data['pickup_longitude'] ?? null,
        ]);
    }

    public function recordDrop(DriverSchedule $trip, array $data): void
    {
        $trip->update([
            'drop' => true,
            'drop_time' => $data['drop_time'] ?? null,
            'drop_latitude' => $data['drop_latitude'] ?? null,
            'drop_longitude' => $data['drop_longitude'] ?? null,
        ]);
    }
}
