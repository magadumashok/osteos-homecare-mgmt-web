<?php

namespace App\Models;

use App\Models\Concerns\ExposesMongoId;
use MongoDB\Laravel\Eloquent\Model;

/** Reference data shared by all homecare centers (master database). */
class Nationality extends Model
{
    use ExposesMongoId;

    protected $connection = 'mongodb_master';

    protected $table = 'nationality';
}
