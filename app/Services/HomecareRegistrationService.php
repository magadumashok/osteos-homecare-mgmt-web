<?php

namespace App\Services;

use App\Models\Homecare;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registers a homecare center: registry entry, owner login, and its own
 * provisioned database.
 */
class HomecareRegistrationService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly AuthService $auth,
    ) {}

    /**
     * @param  array{name: string, owner: string, email: string, mobile: string, password: string,
     *               country: string, region?: ?string, city: string, address?: ?string, center_id?: ?string}  $data
     */
    public function register(array $data): Homecare
    {
        if ($this->auth->findByMobile($data['mobile'])) {
            throw ValidationException::withMessages(['mobile' => 'This mobile number is already registered.']);
        }

        $homecareId = $this->uniqueHomecareId($data['name']);

        $homecare = Homecare::create([
            'homecare_id' => $homecareId,
            'name' => $data['name'],
            'owner' => $data['owner'],
            'center_id' => $data['center_id'] ?? null,
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'country' => $data['country'],
            'region' => $data['region'] ?? null,
            'city' => $data['city'],
            'address' => $data['address'] ?? null,
            'database' => $this->tenants->databaseName($homecareId),
            'status' => Homecare::STATUS_ACTIVE,
        ]);

        $owner = null;

        try {
            // Same shape as the legacy owner account, so the old mobile
            // app and migrated data treat both identically.
            $owner = User::create([
                'username' => $homecareId,
                'homecare_id' => $homecareId,
                'prefix' => substr($homecareId, 0, 3),
                'user_type' => User::TYPE_HOMECARE,
                'roles' => [Permissions::SUPER_ROLE],
                'name' => $data['name'],
                'owner' => $data['owner'],
                'center_id' => $data['center_id'] ?? null,
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'password' => $data['password'],
                'country' => $data['country'],
                'region' => $data['region'] ?? null,
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
            ]);

            $this->tenants->provision($homecare);
        } catch (\Throwable $e) {
            $owner?->delete();
            $homecare->delete();

            throw $e;
        }

        return $homecare;
    }

    /**
     * Slug of the center name, unique across both usernames and homecare
     * ids. (The legacy slug check looked at a field users never had, so
     * two centers with the same name could collide.)
     */
    public function uniqueHomecareId(string $name): string
    {
        $base = Str::slug($name) ?: 'homecare';
        $candidate = $base;

        for ($i = 1; $this->taken($candidate); $i++) {
            $candidate = $base.'-'.$i;
        }

        return $candidate;
    }

    private function taken(string $id): bool
    {
        return User::where('username', $id)->exists()
            || Homecare::where('homecare_id', $id)->exists();
    }
}
