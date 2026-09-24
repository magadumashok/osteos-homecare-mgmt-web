<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Every login (homecare owner, staff, driver, doctor) lives in the master
 * database so authentication never depends on tenant resolution.
 * `homecare_id` ties a user to their homecare center's tenant database.
 *
 * Field names follow the legacy DynamoDB `users` table so migrated
 * documents and the legacy mobile API keep working unchanged.
 */
class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    /** User types stored in `user_type`. */
    public const TYPE_HOMECARE = 'homecare';

    public const TYPE_STAFF = 'staff';

    protected $connection = 'mongodb_master';

    protected $table = 'users';

    protected $fillable = [
        'username', 'homecare_id', 'prefix', 'user_type', 'roles',
        'name', 'full_name', 'firstname', 'lastname', 'owner', 'center_id',
        'email', 'mobile', 'password',
        'country', 'region', 'city', 'address',
        'temporary_address', 'permanent_address',
        'specialization', 'firebase_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'roles' => 'array',
        ];
    }

    public function homecare()
    {
        return $this->belongsTo(Homecare::class, 'homecare_id', 'homecare_id');
    }

    /** @return list<string> */
    public function roleNames(): array
    {
        return array_values(array_filter((array) ($this->roles ?? []), 'is_string'));
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['hid' => $this->homecare_id];
    }
}
