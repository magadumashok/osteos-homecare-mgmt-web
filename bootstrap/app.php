<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function () {
            // Legacy unversioned endpoints for the old mobile app.
            Route::middleware('api')->prefix('api')->group(base_path('routes/legacy.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => Authenticate::class,

            // Switches tenant models to the authenticated user's homecare database.
            'tenant' => ResolveTenant::class,

            // Server-side permission check: permission:staff.manage
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // HTTP errors (missing records, abort(403), ...) in the API envelope.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = match (true) {
                $e->getPrevious() instanceof ModelNotFoundException => 'Record not found.',
                $e->getMessage() !== '' => $e->getMessage(),
                default => Response::$statusTexts[$status] ?? 'Error',
            };

            return response()->json(['success' => false, 'message' => $message, 'data' => []], $status, $e->getHeaders());
        });
    })->create();
