<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/** Reference data shared by all homecare centers (master database). */
class Region extends Model
{
    protected $connection = 'mongodb2';

    protected $table = 'regions';
}
