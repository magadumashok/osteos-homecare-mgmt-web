<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `permission:staff.manage` or `permission:a,b` (any of).
 * Must run after ResolveTenant, since roles live in the tenant database.
 */
class EnsurePermission
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string ...$required): Response
    {
        $user = $request->user();

        foreach ($required as $permission) {
            if ($user && $this->permissions->allows($user, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to do this.',
        ], 403);
    }
}
