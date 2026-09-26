<?php

namespace App\Models;

use App\Casts\NativeArray;
use App\Models\Concerns\ExposesMongoId;
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
    use ExposesMongoId, Notifiable;

    /** User types stored in `user_type`. */
    public const TYPE_HOMECARE = 'homecare';

    public const TYPE_STAFF = 'staff';

    protected $connection = 'mongodb_master';

    protected $table = 'users';

    protected $fillable = [
        'username', 'homecare_id', 'prefix', 'user_type', 'roles',
        'name', 'full_name', 'firstname', 'lastname', 'owner', 'center_id',
        'email', 'mobile', 'alternate_mobile', 'password', 'age', 'identity_type', 'identity_number',
        'country', 'region', 'city', 'address',
        'temporary_address', 'permanent_address', 'works_at',
        'specialization', 'firebase_token', 'digitalsignature', 'profile_image',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'roles' => NativeArray::class,
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

    /**
     * The person's name. The owner account's `name` is the center's name
     * (the legacy shape), so its person is in `owner`.
     */
    public function displayName(): string
    {
        $name = $this->user_type === self::TYPE_HOMECARE ? ($this->owner ?: $this->name) : $this->name;

        return (string) ($name ?: $this->username);
    }

    /** The first role, which the legacy app treated as the user's job. */
    public function primaryRole(): ?string
    {
        return $this->roleNames()[0] ?? null;
    }

    /** Country code of the user's phone number: staff keep it on their address. */
    public function phoneCountry(): ?string
    {
        return $this->country ?: data_get($this->temporary_address, 'country');
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
