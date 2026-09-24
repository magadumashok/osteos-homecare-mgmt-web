<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterHomecareRequest;
use App\Models\Homecare;
use App\Models\User;
use App\Services\AuthService;
use App\Services\HomecareRegistrationService;
use App\Services\PermissionService;
use App\Services\TenantService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly AuthService $auth,
        private readonly TenantService $tenants,
        private readonly PermissionService $permissions,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->attempt($request->string('mobile'), $request->string('password'));

        if (! $result) {
            return $this->response(false, 'The mobile number or password is incorrect.', 401);
        }

        return $this->response(true, 'Logged in', 200, [
            'access_token' => $result['token'],
            'token_type' => 'bearer',
            'expires_in' => $result['expires_in'],
            ...$this->session($result['user']),
        ]);
    }

    public function register(RegisterHomecareRequest $request, HomecareRegistrationService $registration): JsonResponse
    {
        $homecare = $registration->register($request->validated());

        return $this->response(true, 'Homecare center registered. You can now log in with your mobile number.', 201, [
            'homecare_id' => $homecare->homecare_id,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->response(true, '', 200, $this->session($request->user()));
    }

    public function refresh(): JsonResponse
    {
        $result = $this->auth->refresh();

        return $this->response(true, '', 200, [
            'access_token' => $result['token'],
            'token_type' => 'bearer',
            'expires_in' => $result['expires_in'],
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return $this->response(true, 'Logged out');
    }

    /** User, homecare and effective permissions for the frontend. */
    private function session(User $user): array
    {
        if (! $user->homecare_id) {
            return ['user' => $user, 'homecare' => null, 'permissions' => []];
        }

        $this->tenants->use($user->homecare_id);

        return [
            'user' => $user,
            'homecare' => Homecare::where('homecare_id', $user->homecare_id)->first(['homecare_id', 'name', 'city', 'country']),
            'permissions' => $this->permissions->for($user),
        ];
    }
}
