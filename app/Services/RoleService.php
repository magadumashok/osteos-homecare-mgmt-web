<?php

namespace App\Services;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Roles of the current homecare and the permissions each holds. The
 * permissions themselves are defined in code (App\Support\Permissions).
 */
class RoleService
{
    public function __construct(private readonly UserDirectoryService $users) {}

    public function all(): Collection
    {
        return Role::orderBy('name')->get();
    }

    public function findOrFail(string $id): Role
    {
        return Role::where('_id', $id)->firstOrFail();
    }

    /** @return list<array{name: string, description: string}> */
    public function catalogue(): array
    {
        return array_map(
            fn ($name, $description) => ['name' => $name, 'description' => $description],
            array_keys(Permissions::CATALOGUE),
            Permissions::CATALOGUE,
        );
    }

    public function create(array $data): Role
    {
        $this->assertNameFree($data['name']);

        return Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $this->validPermissions($data['permissions'] ?? []),
            'system' => false,
        ]);
    }

    /** Rename or re-describe a role. Built-in roles keep their name (users reference it). */
    public function update(Role $role, array $data): Role
    {
        $name = $data['name'] ?? $role->name;

        if ($name !== $role->name) {
            if ($role->system) {
                throw ValidationException::withMessages(['name' => 'Built-in roles cannot be renamed.']);
            }
            $this->assertNameFree($name, $role);
            // Positional $ renames the matched array element in one update.
            $this->users->members()->where('roles', $role->name)->update(['roles.$' => $name]);
        }

        $role->update(['name' => $name, 'description' => $data['description'] ?? $role->description]);

        return $role;
    }

    /** @param  list<string>  $permissions */
    public function setPermissions(Role $role, array $permissions): void
    {
        if ($role->name === Permissions::SUPER_ROLE) {
            throw ValidationException::withMessages(['permissions' => 'The admin role always has every permission.']);
        }

        $role->update(['permissions' => $this->validPermissions($permissions)]);
    }

    /** @return array<string, true> permission => true, the legacy checkbox format */
    public function permissionMap(Role $role): array
    {
        return array_fill_keys(Permissions::normalize((array) $role->permissions), true);
    }

    public function delete(Role $role): void
    {
        if ($role->system) {
            throw ValidationException::withMessages(['role' => 'Built-in roles cannot be deleted.']);
        }
        if ($this->users->members()->where('roles', $role->name)->exists()) {
            throw ValidationException::withMessages(['role' => 'Remove this role from its staff members first.']);
        }

        $role->delete();
    }

    /** @return list<string> */
    private function validPermissions(array $permissions): array
    {
        $unknown = array_filter($permissions, fn ($p) => ! is_string($p) || ! Permissions::exists($p));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => 'Unknown permission: '.implode(', ', array_map('strval', $unknown)).'.']);
        }

        return array_values(array_unique($permissions));
    }

    private function assertNameFree(string $name, ?Role $except = null): void
    {
        $taken = Role::where('name', $name)
            ->when($except, fn ($q) => $q->where('_id', '!=', $except->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }
    }
}
