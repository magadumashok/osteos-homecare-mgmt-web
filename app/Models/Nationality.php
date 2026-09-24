<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/** Reference data shared by all homecare centers (master database). */
class Nationality extends Model
{
    protected $connection = 'mongodb_master';

    protected $table = 'nationality';
}
