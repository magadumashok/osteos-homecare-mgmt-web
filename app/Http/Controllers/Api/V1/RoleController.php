<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly RoleService $roles) {}

    public function index(): JsonResponse
    {
        return $this->response(true, '', 200, $this->roles->all());
    }

    /** The permission catalogue roles are built from. */
    public function permissions(): JsonResponse
    {
        return $this->response(true, '', 200, $this->roles->catalogue());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
        ], ['name.regex' => 'Use lowercase letters, digits, - or _ for the role name.']);

        return $this->response(true, 'Role created.', 201, $this->roles->create($data));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->response(true, 'Role updated.', 200, $this->roles->update($this->roles->findOrFail($id), $data));
    }

    public function updatePermissions(Request $request, string $id): JsonResponse
    {
        $permissions = $request->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string']])['permissions'];

        $role = $this->roles->findOrFail($id);
        $this->roles->setPermissions($role, $permissions);

        return $this->response(true, 'Permissions updated.', 200, $role->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $this->roles->delete($this->roles->findOrFail($id));

        return $this->response(true, 'Role deleted.');
    }
}
