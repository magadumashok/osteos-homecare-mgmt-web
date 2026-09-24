<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;

/**
 * Resolves a user's effective permissions from their role names and the
 * roles defined in their homecare's database. Must be called after the
 * tenant has been resolved.
 */
class PermissionService
{
    /** @var array<string, list<string>> user key => permissions, cached for the request */
    private array $cache = [];

    /** @return list<string> */
    public function for(User $user): array
    {
        $key = (string) $user->getKey();

        return $this->cache[$key] ??= $this->resolve($user);
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array($permission, $this->for($user), true);
    }

    /** @return list<string> */
    private function resolve(User $user): array
    {
        $roleNames = $user->roleNames();

        // The center's owner account and admins are never locked out,
        // whatever happens to the stored admin role.
        if ($user->user_type === User::TYPE_HOMECARE || in_array(Permissions::SUPER_ROLE, $roleNames, true)) {
            return Permissions::all();
        }

        if ($roleNames === []) {
            return [];
        }

        $stored = Role::whereIn('name', $roleNames)->pluck('permissions')->flatten(1);

        return Permissions::normalize($stored);
    }
}
