<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Registry of homecare centers (tenants), kept in the master database.
 *
 * The legacy app had no such collection: a center was just a `users`
 * document with user_type "homecare" whose username doubled as the
 * homecare_id. The data migration builds this registry from those users.
 */
class Homecare extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $connection = 'mongodb_master';

    protected $table = 'homecares';

    protected $fillable = [
        'homecare_id', 'name', 'owner', 'center_id',
        'email', 'mobile', 'country', 'region', 'city', 'address',
        'database', 'status',
    ];

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }
}
