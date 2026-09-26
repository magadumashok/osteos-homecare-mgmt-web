<?php

namespace App\Models;

use App\Models\Concerns\ExposesMongoId;
use MongoDB\Laravel\Eloquent\Model;

/** Reference data shared by all homecare centers (master database). */
class Country extends Model
{
    use ExposesMongoId;

    protected $connection = 'mongodb2';

    protected $table = 'countries';
}
