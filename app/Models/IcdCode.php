<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * ICD diagnosis codes (master database). Field names are capitalised
 * (`Name`, `Description`) as in the imported source data.
 */
class IcdCode extends Model
{
    protected $connection = 'mongodb_master';

    protected $table = 'icd_codes';
}
