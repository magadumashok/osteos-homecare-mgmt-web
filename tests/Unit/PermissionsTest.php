<?php

namespace Tests\Unit;

use App\Support\Permissions;
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    public function test_wildcard_expands_to_full_catalogue(): void
    {
        $this->assertSame(Permissions::all(), Permissions::normalize(['*']));
    }

    public function test_legacy_names_are_translated(): void
    {
        $result = Permissions::normalize(['manage_roles', 'manage_permissions']);

        $this->assertSame(['roles.manage'], $result);
    }

    public function test_unknown_and_non_string_values_are_dropped(): void
    {
        $result = Permissions::normalize(['patients.view', 'made.up', 42, null]);

        $this->assertSame(['patients.view'], $result);
    }

    public function test_default_roles_only_use_catalogue_permissions(): void
    {
        foreach (Permissions::DEFAULT_ROLES as $name => $role) {
            foreach ($role['permissions'] as $permission) {
                $this->assertTrue(
                    $permission === '*' || Permissions::exists($permission),
                    "Role '{$name}' uses unknown permission '{$permission}'."
                );
            }
        }
    }
}
