<?php

namespace App\Services;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Builder;

/**
 * Lookups of the current homecare's users.
 *
 * Users live in the shared master database, so every query here is scoped
 * to the current homecare_id. (Several legacy queries were not, and could
 * pick another center's admin or driver.) Roles are stored as an array,
 * so role filters use array membership, not equality with ["role"].
 */
class UserDirectoryService
{
    public function __construct(private readonly TenantService $tenants) {}

    /** Every user of the current homecare. */
    public function members(): Builder
    {
        return User::where('homecare_id', $this->tenants->current());
    }

    public function find(?string $username): ?User
    {
        return $username ? $this->members()->where('username', $username)->first() : null;
    }

    public function findByKey(string $id): ?User
    {
        return $this->members()->where('_id', $id)->first();
    }

    /** @return Collection<int, User> */
    public function withRole(string $role): Collection
    {
        return $this->members()->where('roles', $role)->get();
    }

    /** Users holding none of the given roles. */
    public function withoutRoles(array $roles): Collection
    {
        return $this->members()->whereNotIn('roles', $roles)->get();
    }

    /** Driver by display name (the legacy schedule form sends the driver's name). */
    public function driverNamed(?string $name): ?User
    {
        return $name ? $this->members()->where('roles', 'transport')->where('name', $name)->first() : null;
    }

    /** The account notifications for the center go to: its owner, else an admin. */
    public function owner(): ?User
    {
        return $this->members()->where('user_type', User::TYPE_HOMECARE)->first()
            ?? $this->members()->where('roles', Permissions::SUPER_ROLE)->first();
    }

    /**
     * Users by username in one query, for decorating lists (replaces the
     * legacy one-query-per-row lookups).
     *
     * @param  iterable<?string>  $usernames
     * @return Collection<string, User>
     */
    public function byUsername(iterable $usernames): Collection
    {
        $usernames = collect($usernames)->filter()->unique()->values();

        if ($usernames->isEmpty()) {
            return collect();
        }

        return $this->members()->whereIn('username', $usernames->all())->get()->keyBy('username');
    }
}
