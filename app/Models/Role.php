<?php

namespace App\Models;

/**
 * A named set of permissions, defined per homecare center.
 * Permission names come from App\Support\Permissions.
 */
class Role extends TenantModel
{
    protected $table = 'roles';

    protected $fillable = ['name', 'description', 'permissions', 'system'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'system' => 'boolean',
        ];
    }
}
