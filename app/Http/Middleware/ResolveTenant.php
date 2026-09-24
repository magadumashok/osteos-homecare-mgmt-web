<?php

namespace App\Http\Middleware;

use App\Models\Homecare;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points tenant models at the authenticated user's homecare database.
 * Must run after authentication (jwt.auth).
 */
class ResolveTenant
{
    public function __construct(private readonly TenantService $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $homecareId = $user?->homecare_id;

        if (! is_string($homecareId) || $homecareId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not linked to a homecare center.',
            ], 403);
        }

        $homecare = Homecare::where('homecare_id', $homecareId)->first();

        if ($homecare && ! $homecare->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'This homecare center is suspended. Contact support.',
            ], 403);
        }

        $this->tenants->use($homecareId);

        return $next($request);
    }
}
