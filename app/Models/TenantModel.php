<?php

namespace App\Models;

use App\Models\Concerns\ExposesMongoId;
use App\Services\TenantService;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Base class for every collection that lives in a homecare center's own
 * database. The connection's database is switched per request by
 * TenantService::use(); models never filter by homecare_id themselves.
 *
 * New documents are still stamped with `homecare_id`, as every legacy
 * document was, so migrated and new data look the same to the old app.
 */
abstract class TenantModel extends Model
{
    use ExposesMongoId;

    protected $connection = 'mongodb_tenant';

    protected static function booted(): void
    {
        static::creating(function (TenantModel $model) {
            if ($model->getAttribute('homecare_id') === null) {
                $model->setAttribute('homecare_id', app(TenantService::class)->current());
            }
        });
    }
}
