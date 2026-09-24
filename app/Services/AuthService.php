<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * Mobile-number + password login, shared by the v1 and legacy endpoints.
 */
class AuthService
{
    public function findByMobile(string $mobile): ?User
    {
        $mobile = trim($mobile);
        if ($mobile === '') {
            return null;
        }

        // Migrated DynamoDB data may hold the number as a string or a number.
        return User::where('mobile', $mobile)
            ->when(ctype_digit($mobile), fn ($q) => $q->orWhere('mobile', (int) $mobile))
            ->first();
    }

    /**
     * @param  int|null  $ttl  token lifetime in minutes; null uses JWT_TTL
     * @return array{token: string, user: User, expires_in: int}|null
     */
    public function attempt(string $mobile, string $password, ?int $ttl = null): ?array
    {
        $user = $this->findByMobile($mobile);

        if (! $user || ! is_string($user->password) || ! Hash::check($password, $user->password)) {
            return null;
        }

        $guard = $this->guard();
        if ($ttl !== null) {
            $guard->setTTL($ttl);
        }

        return [
            'token' => $guard->login($user),
            'user' => $user,
            'expires_in' => $guard->factory()->getTTL() * 60,
        ];
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }

    /** @return array{token: string, expires_in: int} */
    public function refresh(): array
    {
        $guard = $this->guard();

        return [
            'token' => $guard->refresh(),
            'expires_in' => $guard->factory()->getTTL() * 60,
        ];
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard */
        return auth('api');
    }
}
