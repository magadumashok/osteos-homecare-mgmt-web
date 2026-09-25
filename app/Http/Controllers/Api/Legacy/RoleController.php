<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Legacy roles and permissions endpoints (formerly RolesController and
 * PermissionController). Permissions are now defined in code, so the
 * permission list is read-only.
 */
class RoleController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly RoleService $roles) {}

    /** GET /api/roles — bare array. */
    public function index(): JsonResponse
    {
        return response()->json($this->roles->all());
    }

    /** POST /api/roles {name, description} */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['name' => 'required|string|max:60', 'description' => 'required']);
        if ($validator->fails()) {
            return $this->response(false, 'Something went wrong', 500, $validator->messages()->toJson());
        }

        try {
            $this->roles->create($request->only(['name', 'description']));
        } catch (ValidationException) {
            return $this->response(false, 'Name already present in the list', 500);
        }

        return $this->response(true, 'Role has been created successfully', 200);
    }

    /** GET /api/roles/{id}/edit */
    public function edit(string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->roles->findOrFail($id)->toArray());
    }

    /** PUT /api/roles/{id} {name, description} */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), ['name' => 'required|string|max:60', 'description' => 'required']);
        if ($validator->fails()) {
            return $this->response(false, 'Something went wrong', 500, $validator->messages()->toJson());
        }

        try {
            $this->roles->update($this->roles->findOrFail($id), $request->only(['name', 'description']));
        } catch (ValidationException $e) {
            return $this->response(false, collect($e->errors())->flatten()->first(), 500);
        }

        // Legacy wording kept.
        return $this->response(true, 'Permission has been updated successfully', 200);
    }

    /** POST /api/addpermissiontorole {id, formdata: {permission: bool}} */
    public function updatePermissions(Request $request): JsonResponse
    {
        $permissions = array_keys(array_filter((array) $request->input('formdata', [])));

        try {
            $this->roles->setPermissions($this->roles->findOrFail((string) $request->input('id')), $permissions);
        } catch (ValidationException $e) {
            return $this->response(false, collect($e->errors())->flatten()->first(), 500);
        }

        return $this->response(true, 'Permissions Updated successfully', 200);
    }

    /** GET /api/existingpermissions/{id} — {permission: true} */
    public function permissionsOf(string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->roles->permissionMap($this->roles->findOrFail($id)));
    }

    /** GET /api/permissions — the catalogue, shaped like the old permission documents. */
    public function permissions(): JsonResponse
    {
        return response()->json(array_map(
            fn (array $permission) => ['_id' => $permission['name'], ...$permission],
            $this->roles->catalogue(),
        ));
    }

    /** GET /api/permissions/{id}/edit */
    public function permission(string $id): JsonResponse
    {
        $permission = collect($this->roles->catalogue())->firstWhere('name', $id);

        return $permission
            ? $this->response(true, '', 200, ['_id' => $permission['name'], ...$permission])
            : $this->response(false, 'Permission not found.', 404);
    }

    /** POST/PUT /api/permissions — permissions are part of the application now. */
    public function permissionsReadOnly(): JsonResponse
    {
        return $this->response(false, 'Permissions are defined by the system and cannot be added or edited.', 403);
    }
}
