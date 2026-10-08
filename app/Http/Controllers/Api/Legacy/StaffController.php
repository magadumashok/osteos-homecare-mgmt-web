<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Services\ReferenceDataService;
use App\Services\StaffService;
use App\Services\UserDirectoryService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Legacy staff endpoints (formerly UsersController and parts of MobileUserController). */
class StaffController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly StaffService $staff) {}

    /** GET /api/users */
    public function index(): JsonResponse
    {
        return $this->response(true, '', 200, $this->staff->all());
    }

    /** POST /api/users {name, mobile, password, role, userID, email, age, id, alternate_mobile, temporary_address} */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required', 'mobile' => 'required', 'password' => 'required|min:6',
            'role' => 'required', 
            // 'userID' => 'required|alpha_dash', 
            'address' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Something went wrong', 500, $validator->messages()->toJson());
        }

        try {
            $this->staff->create($request->only([
                'name', 'mobile', 'password', 'role', 'email', 'age', 'address',
            ]));
        } catch (ValidationException $e) {
            // The legacy web form checks these exact messages.
            return match (array_key_first($e->errors())) {
                'mobile' => $this->response(true, 'mobile number already exists', 200),
                'userID' => $this->response(true, 'username already exists', 200),
                default => $this->response(false, 'Something went wrong', 500, json_encode($e->errors())),
            };
        }

        return $this->response(true, 'User has been Created Successfully', 200);
    }

    /** GET /api/users/{id}/edit — the user plus region and city lists for the form. */
    public function edit(string $id, ReferenceDataService $reference): JsonResponse
    {
        $user = $this->staff->findOrFail($id);
        $address = (array) ($user->address ?? []);

        return $this->response(true, '', 200, [
            'userdata' => $user->toArray(),
            'regions' => isset($address['country']) ? $reference->regions((string) $address['country']) : [],
            'cities' => isset($address['region']) ? $reference->cities((string) $address['region']) : [],
        ]);
    }

    /** PUT /api/users/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $this->staff->update($this->staff->findOrFail($id), $request->only(['name', 'mobile', 'email', 'age',  'address']));
        } catch (ValidationException) {
            return $this->response(true, 'mobile number already exists', 200);
        }

        return $this->response(true, 'User Updated successfully', 200);
    }

    /** POST /api/delete-staff-member/{id}/{mobile} — {id} is the username. */
    public function destroy(Request $request, string $id, string $mobile, UserDirectoryService $users): JsonResponse
    {
        $user = $users->find($id);

        if ($user && (string) $user->mobile === $mobile) {
            try {
                $this->staff->delete($user, $request->user());
            } catch (ValidationException $e) {
                return $this->response(false, collect($e->errors())->flatten()->first(), 403);
            }
        }

        return $this->response(true, 'success', 200);
    }

    /** POST /api/addroletouser {id, formdata: {role: bool}} */
    public function updateRoles(Request $request): JsonResponse
    {
        $roles = array_keys(array_filter((array) $request->input('formdata', [])));

        try {
            $this->staff->setRoles($this->staff->findOrFail((string) $request->input('id')), $roles);
        } catch (ValidationException $e) {
            return $this->response(false, collect($e->errors())->flatten()->first(), 500);
        }

        return $this->response(true, 'Roles Updated successfully', 200);
    }

    /** GET /api/existingroles/{id} — {role: true} */
    public function roles(string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->staff->roleMap($this->staff->findOrFail($id)));
    }

    /** GET /api/all_users — everyone except drivers, bare array. */
    public function exceptDrivers(): JsonResponse
    {
        return response()->json($this->staff->exceptDrivers());
    }

    /** GET /api/all_nurses — staff who do home visits. */
    public function visiting(): JsonResponse
    {
        $staff = $this->staff->visitingStaff();

        return $staff->isEmpty()
            ? $this->response(true, 'no data found', 200, '')
            : $this->response(true, 'success', 200, $staff);
    }

    /** POST /api/get-relevant-staff {data: {role}} */
    public function withRole(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->staff->withRole((string) $request->input('data.role')));
    }

    /** GET /api/get-all-users — colleagues with full phone numbers. */
    public function directory(Request $request): JsonResponse
    {
        return $this->response(true, 'success', 200, $this->staff->directory($request->user()));
    }
}
