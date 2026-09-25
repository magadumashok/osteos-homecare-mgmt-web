<?php

namespace Tests\Feature;

use App\Support\Permissions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Every authenticated API route must check a permission on the server,
 * except the few that only act on the caller's own account.
 */
class RoutePermissionsTest extends TestCase
{
    /** Authenticated routes that deliberately need no permission. */
    private const OWN_ACCOUNT_ROUTES = [
        'api/logout',
        'api/get-user-details',
        'api/update-firebase-token',
        'api/user_feedback',
        'api/get-all-users',
        'api/get_service_packages',
        'api/get-icd-codes',
        'api/v1/auth/me',
        'api/v1/auth/logout',
        'api/v1/account/firebase-token',
        'api/v1/account/feedback',
        'api/v1/staff/directory',
        'api/v1/reference/service-packages',
        'api/v1/reference/icd-codes',
    ];

    public function test_authenticated_routes_check_a_permission(): void
    {
        $missing = $this->authenticatedRoutes()
            ->reject(fn (Route $route) => in_array($route->uri(), self::OWN_ACCOUNT_ROUTES, true))
            ->reject(fn (Route $route) => $this->permissionsOf($route) !== [])
            ->map(fn (Route $route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $missing, "Routes without a permission: middleware:\n".implode("\n", $missing));
    }

    public function test_route_permissions_exist_in_the_catalogue(): void
    {
        $unknown = $this->authenticatedRoutes()
            ->flatMap(fn (Route $route) => $this->permissionsOf($route))
            ->reject(fn (string $permission) => Permissions::exists($permission))
            ->unique()
            ->values()
            ->all();

        $this->assertSame([], $unknown);
    }

    public function test_authenticated_routes_resolve_the_tenant(): void
    {
        $withoutTenant = $this->authenticatedRoutes()
            ->reject(fn (Route $route) => in_array('tenant', $route->gatherMiddleware(), true))
            ->map(fn (Route $route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $withoutTenant);
    }

    private function authenticatedRoutes()
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/'))
            ->filter(fn (Route $route) => in_array('jwt.auth', $route->gatherMiddleware(), true));
    }

    /** @return list<string> */
    private function permissionsOf(Route $route): array
    {
        return collect($route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'))
            ->flatMap(fn (string $middleware) => explode(',', substr($middleware, strlen('permission:'))))
            ->values()
            ->all();
    }
}
