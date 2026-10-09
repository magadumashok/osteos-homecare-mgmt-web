<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterHomecareRequest;
use App\Models\Homecare;
use App\Models\User;
use App\Services\AuthService;
use App\Services\HomecareRegistrationService;
use App\Services\OtpService;
use App\Services\PermissionService;
use App\Services\TenantService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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

        return $this->loggedIn($result);
    }

    /**
     * Text a one-time code for OTP login or a password reset. The reply is
     * the same whether or not the number is registered.
     */
    public function sendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'purpose' => ['required', Rule::in(OtpService::PURPOSES)],
        ]);

        if ($user = $this->auth->findByMobile($data['mobile'])) {
            $otp->send($user, $data['purpose']);
        }

        return $this->response(true, 'If this number is registered, a code has been sent to it.');
    }

    public function otpLogin(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->auth->findByMobile($data['mobile']);

        if (! $user || ! $otp->verify($user, OtpService::LOGIN, $data['otp'])) {
            return $this->response(false, 'The code is incorrect or has expired.', 401);
        }

        return $this->loggedIn($this->auth->issue($user));
    }

    public function resetPassword(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->auth->findByMobile($data['mobile']);

        if (! $user || ! $otp->verify($user, OtpService::RESET, $data['otp'])) {
            return $this->response(false, 'The code is incorrect or has expired.', 422);
        }

        $this->auth->changePassword($user, $data['password']);

        return $this->response(true, 'Password changed. You can now log in with your new password.');
    }

    /** Change the signed-in user's own password. */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! is_string($user->password) || ! Hash::check($data['current_password'], $user->password)) {
            return $this->response(false, 'The current password is incorrect.', 422);
        }

        $this->auth->changePassword($user, $data['password']);

        return $this->response(true, 'Password changed.');
    }

    /** @param  array{token: string, user: User, expires_in: int}  $result */
    private function loggedIn(array $result): JsonResponse
    {
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
            'homecare' => Homecare::where('homecare_id', $user->homecare_id)->first(['homecare_id', 'name', 'owner', 'email', 'mobile', 'address', 'city', 'region', 'country', 'center_id']),
            'permissions' => $this->permissions->for($user),
        ];
    }
}
