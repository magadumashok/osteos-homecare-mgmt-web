<?php

namespace App\Services;

use App\Models\StaffDutySchedule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which patients a user may see. Only `patients.view_all` (admins, and any
 * role given it on purpose) sees all of them; no other permission implies
 * it. Everyone else sees a patient only while assigned: a duty with them
 * that has not ended or been cancelled. Enforced here on the server; the
 * apps just show less.
 */
class PatientAccessService
{
    /** @var array<string, list<string>> username => patient ids, for the request */
    private array $assigned = [];

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly TenantService $tenants,
    ) {}

    public function seesAll(User $user): bool
    {
        return $this->permissions->allows($user, 'patients.view_all');
    }

    public function canSee(User $user, ?string $patientId): bool
    {
        return $this->seesAll($user) || in_array($patientId, $this->assignedIds($user), true);
    }

    /** @return list<string> patients with a duty of this user that is still running or to come */
    public function assignedIds(User $user): array
    {
        return $this->assigned[$user->username] ??= $this->patientIds(
            StaffDutySchedule::where('staff_id', $user->username)
                ->where('not_attended', '!=', true)
                ->where(function ($q) {
                    $today = $this->tenants->today();
                    $q->where('end_date', '>=', $today)->orWhere('date', '>=', $today);
                }),
        );
    }

    /** Only the rows about patients this user may see. */
    public function filter(User $user, Collection $rows, string $key = 'patient_id'): Collection
    {
        return $this->seesAll($user) ? $rows : $this->only($rows, $this->assignedIds($user), $key);
    }

    /** Every duty for those who see all patients; for anyone else only their own, not a colleague's on the same patient. */
    public function ownDuties(User $user, Collection $duties): Collection
    {
        return $this->seesAll($user) ? $duties : $duties->filter(fn ($duty) => data_get($duty, 'staff_id') === $user->username)->values();
    }

    /**
     * Discharged patients have no running duty, so here staff keep the
     * patients they looked after.
     */
    public function filterPast(User $user, Collection $rows, string $key = 'username'): Collection
    {
        if ($this->seesAll($user)) {
            return $rows;
        }

        return $this->only($rows, $this->patientIds(StaffDutySchedule::where('staff_id', $user->username)), $key);
    }

    private function only(Collection $rows, array $ids, string $key): Collection
    {
        return $rows->filter(fn ($row) => in_array(data_get($row, $key), $ids, true))->values();
    }

    /** @return list<string> */
    private function patientIds($duties): array
    {
        return $duties->pluck('patient_id')->filter()->unique()->values()->all();
    }
}
