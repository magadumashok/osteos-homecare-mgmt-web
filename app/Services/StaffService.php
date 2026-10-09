<?php

namespace App\Services;

use App\Mail\StaffMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff accounts of the current homecare (stored with every other login
 * in the master database).
 */
class StaffService
{
    /** Letter in the generated username for each role, as in the legacy app. */
    private const ROLE_LETTERS = [
        'admin' => 'a', 'nurse' => 'n', 'physiotherapist' => 'p', 'doctor' => 'd',
        'staff' => 's', 'maintenance' => 'm', 'transport' => 't',
    ];

    public function __construct(
        private readonly TenantService $tenants,
        private readonly UserDirectoryService $users,
        private readonly AuthService $auth,
        private readonly ReferenceDataService $reference,
    ) {}

    public function all(): Collection
    {
        return $this->users->members()->orderBy('name')->get();
    }

    public function findOrFail(string $id): User
    {
        return $this->users->findByKey($id) ?? throw (new ModelNotFoundException)->setModel(User::class, [$id]);
    }

    /**
     * Username is "<first 3 of homecare_id><role letter><user id>", slugged.
     *
     * @throws ValidationException when the mobile number or username is taken
     */
    public function create(array $data): User
    {
        $role = $data['role'];
        $this->assertRolesExist([$role]);

        if ($this->auth->findByMobile((string) $data['mobile'])) {
            throw ValidationException::withMessages(['mobile' => 'This mobile number is already registered.']);
        }

        $letter = self::ROLE_LETTERS[$role] ?? substr($role, 0, 1);
        $username = Str::slug(substr((string) $this->tenants->current(), 0, 3).$letter.$data['mobile']);

        if (User::where('username', $username)->exists()) {
            throw ValidationException::withMessages(['mobile' => "The username {$username} is already taken."]);
        }

        return User::create([
            ...array_intersect_key($data, array_flip(['name', 'email', 'mobile', 'age', 'address', 'address', 'password'])),
            'identity_number' => $data['identity_number'] ?? $data['id'] ?? null,
            'username' => $username,
            'homecare_id' => $this->tenants->current(),
            'user_type' => User::TYPE_STAFF,
            'roles' => [$role],
            // 'works_at' => [
            //     'user_id' => $this->tenants->current(),
            //     'type' => User::TYPE_HOMECARE,
            //     'type_name' => $this->tenants->displayName(),
            // ],
        ]);
    }

    public function update(User $user, array $data): User
    {
        $mobile = $data['mobile'] ?? null;
        if ($mobile !== null && (string) $mobile !== (string) $user->mobile) {
            $other = $this->auth->findByMobile((string) $mobile);
            if ($other && ! $other->is($user)) {
                throw ValidationException::withMessages(['mobile' => 'This mobile number is already registered.']);
            }
        }

        $changes = array_intersect_key($data, array_flip(['name', 'email', 'mobile', 'age', 'address']));
        if (array_key_exists('identity_number', $data) || array_key_exists('id', $data)) {
            $changes['identity_number'] = $data['identity_number'] ?? $data['id'];
        }

        $user->update($changes);

        return $user;
    }

    public function delete(User $user, User $actingUser): void
    {
        if ($user->user_type === User::TYPE_HOMECARE) {
            throw ValidationException::withMessages(['user' => 'The homecare owner account cannot be deleted.']);
        }
        if ($user->is($actingUser)) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();
    }

    /** @param  list<string>  $roles */
    public function setRoles(User $user, array $roles): void
    {
        $roles = array_values(array_unique($roles));
        $this->assertRolesExist($roles);

        if ($user->user_type === User::TYPE_HOMECARE && ! in_array('admin', $roles, true)) {
            throw ValidationException::withMessages(['roles' => 'The homecare owner must keep the admin role.']);
        }

        $user->update(['roles' => $roles]);
    }

    /** @return array<string, true> role => true, the legacy checkbox format */
    public function roleMap(User $user): array
    {
        return array_fill_keys($user->roleNames(), true);
    }

    /**
     * Everyone else in the homecare with full phone numbers, for calling
     * from the app.
     */
    public function directory(User $except): Collection
    {
        return $this->users->members()
            ->where('username', '!=', $except->username)
            ->orderBy('name')
            ->get()
            ->each(function (User $user) {
                $user->user_mobile = $this->reference->userNumber($user) ?? '';
                $user->user_alternate_mobile = $this->reference->userNumber($user, 'alternate_mobile') ?? '';
            });
    }

    /** Everyone except drivers (who can take a lead). */
    public function exceptDrivers(): Collection
    {
        return $this->users->withoutRoles(['transport']);
    }

    /** Staff who do home visits: not admins, drivers or doctors (legacy "nurses" list). */
    public function visitingStaff(): Collection
    {
        return $this->users->withoutRoles(['admin', 'transport', 'doctor']);
    }

    public function withRole(string $role): Collection
    {
        return $this->users->withRole($role);
    }

    /** Email everyone with a role (e.g. a doctor asking nurses for a missing document). */
    public function messageRole(string $role, User $from, string $subject, string $body): int
    {
        $recipients = $this->users->withRole($role)->filter(fn (User $user) => filled($user->email));

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email, $recipient->name)->queue(new StaffMessage($recipient->name, $from->name, $subject, $body));
        }

        return $recipients->count();
    }

    /** @param  list<string>  $roles */
    private function assertRolesExist(array $roles): void
    {
        $known = Role::whereIn('name', $roles)->pluck('name')->all();
        $unknown = array_diff($roles, $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['role' => 'Unknown role: '.implode(', ', $unknown).'.']);
        }
    }
}
