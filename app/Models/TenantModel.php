<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Base class for every collection that lives in a homecare center's own
 * database. The connection's database is switched per request by
 * TenantService::use(); models never filter by homecare_id themselves.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'mongodb_tenant';
}
