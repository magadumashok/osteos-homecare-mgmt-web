<?php

namespace App\Models;

/** A driver's pickup and drop of a staff member for one duty. */
class DriverSchedule extends TenantModel
{
    protected $table = 'driver_schedule';

    protected $fillable = [
        'driver_id', 'staff_id', 'address',
        'date', 'end_date', 'start_time', 'end_time',
        'pickup', 'pickup_time', 'pickup_latitude', 'pickup_longitude',
        'drop', 'drop_time', 'drop_latitude', 'drop_longitude',
    ];
}
