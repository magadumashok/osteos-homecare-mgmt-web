<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/** Reference data shared by all homecare centers (master database). */
class City extends Model
{
    protected $connection = 'mongodb2';

    protected $table = 'cities';
}
