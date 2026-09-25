<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffRequest;
use App\Services\PatientService;
use App\Services\StaffService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly StaffService $staff) {}

    /**
     * ?role=nurse                one role
     * ?group=visiting            staff who do home visits (not admins, drivers or doctors)
     * ?group=except-drivers      everyone who can take a lead
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'role' => ['nullable', 'string', 'max:60'],
            'group' => ['nullable', Rule::in(['visiting', 'except-drivers'])],
        ]);

        $staff = match (true) {
            isset($filters['role']) => $this->staff->withRole($filters['role']),
            ($filters['group'] ?? null) === 'visiting' => $this->staff->visitingStaff(),
            ($filters['group'] ?? null) === 'except-drivers' => $this->staff->exceptDrivers(),
            default => $this->staff->all(),
        };

        return $this->response(true, '', 200, $staff);
    }

    /** Colleagues with full phone numbers, for calling from the app. */
    public function directory(Request $request): JsonResponse
    {
        return $this->response(true, '', 200, $this->staff->directory($request->user()));
    }

    public function store(StaffRequest $request): JsonResponse
    {
        return $this->response(true, 'Staff member added.', 201, $this->staff->create($request->validated()));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response(true, '', 200, $this->staff->findOrFail($id));
    }

    public function update(StaffRequest $request, string $id): JsonResponse
    {
        $user = $this->staff->update($this->staff->findOrFail($id), $request->validated());

        return $this->response(true, 'Staff member updated.', 200, $user);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->staff->delete($this->staff->findOrFail($id), $request->user());

        return $this->response(true, 'Staff member removed.');
    }

    public function updateRoles(Request $request, string $id): JsonResponse
    {
        $roles = $request->validate(['roles' => ['required', 'array', 'min:1'], 'roles.*' => ['string']])['roles'];

        $user = $this->staff->findOrFail($id);
        $this->staff->setRoles($user, $roles);

        return $this->response(true, 'Roles updated.', 200, $user->fresh());
    }

    /** Patients a staff member has had duties with. */
    public function patients(string $id, PatientService $patients): JsonResponse
    {
        return $this->response(true, '', 200, $patients->patientsOfStaff($this->staff->findOrFail($id)->username));
    }
}
