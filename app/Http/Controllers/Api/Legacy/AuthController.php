<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Services\AuthService;
use App\Services\HomecareRegistrationService;
use App\Services\PermissionService;
use App\Services\TenantService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Old /api/* auth endpoints, kept request- and response-compatible for the
 * legacy mobile app until it is migrated to /api/v1.
 */
class AuthController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly AuthService $auth) {}

    /** POST /api/login — legacy web login. */
    public function login(Request $request, TenantService $tenants, PermissionService $permissions): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login_mobile' => 'required',
            'login_password' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Something went wrong', 500, $validator->messages()->toJson());
        }

        $result = $this->auth->attempt((string) $request->login_mobile, (string) $request->login_password, $this->legacyTtl());
        if (! $result) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = $result['user'];
        if ($user->homecare_id) {
            $tenants->use($user->homecare_id);
        }

        return response()->json([
            'access_token' => $result['token'],
            'token_type' => 'bearer',
            'expires_in' => intdiv($result['expires_in'], 60),
            'user' => $user,
            'permissions' => $user->homecare_id ? $permissions->for($user) : [],
            'key' => null,
        ]);
    }

    /** POST /api/user_login — legacy mobile staff login. */
    public function userLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['login_mobile' => 'required']);
        if ($validator->fails()) {
            return $this->response(false, 'Something went wrong', 500, $validator->messages()->toJson());
        }

        $result = $this->auth->attempt((string) $request->login_mobile, (string) $request->login_password, $this->legacyTtl());
        if (! $result) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'access_token' => $result['token'],
            'token_type' => 'bearer',
            'expires_in' => intdiv($result['expires_in'], 60),
            'user' => $result['user'],
            'key' => null,
        ]);
    }

    /** POST /api/register — legacy homecare center registration. */
    public function register(Request $request, HomecareRegistrationService $registration): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'center_name' => 'required|string',
            'owner_name' => 'required|string',
            'city' => 'required',
            'country' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'mobile' => 'required',
            'who' => 'required|in:homecare',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Something went wrong', 500, $validator->messages()->toJson());
        }

        // Legacy quirk kept for the old client: a duplicate number is a 200.
        if ($this->auth->findByMobile((string) $request->mobile)) {
            return $this->response(true, 'mobile number already exists', 200);
        }

        try {
            $registration->register([
                'name' => $request->center_name,
                'owner' => $request->owner_name,
                'email' => $request->email,
                'mobile' => (string) $request->mobile,
                'password' => $request->password,
                'country' => $request->country,
                'region' => Region::where('code', $request->region_ob)->value('name'),
                'city' => $request->city,
                'address' => $request->address,
                'center_id' => $request->identity,
            ]);
        } catch (ValidationException $e) {
            return $this->response(false, 'Something went wrong', 500, json_encode($e->errors()));
        }

        return $this->response(true, 'success', 200);
    }

    /** GET /api/logout */
    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return $this->response(true, 'User has been logged Out Successfully', 200);
    }

    /**
     * POST /api/check-user-mobile and /api/change-forgot-password.
     *
     * The legacy flow returned the OTP to the client and then let anyone
     * set a new password for any mobile number, so it is disabled. Staff
     * ask their homecare admin for a reset until the app gets the new flow.
     */
    public function forgotPasswordDisabled(): JsonResponse
    {
        return $this->response(false, 'Password reset from the app is no longer available. Ask your homecare administrator to reset your password.', 200);
    }

    private function legacyTtl(): int
    {
        return (int) config('homecare.legacy.token_ttl');
    }
}
