<?php

namespace App\Services;

use App\Jobs\SendSms;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * One-time codes sent by SMS for OTP login and forgot-password.
 *
 * Replaces the legacy flow, which returned the generated code to the app
 * (so the check happened on the phone) and then changed the password of
 * any mobile number without verifying anything. Here the code never
 * leaves the server: only its hash is cached, it expires, it can be used
 * once, and a few wrong guesses burn it.
 */
class OtpService
{
    public const LOGIN = 'login';

    public const RESET = 'reset';

    public const PURPOSES = [self::LOGIN, self::RESET];

    private const TTL_SECONDS = 300;

    private const RESEND_AFTER_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly ReferenceDataService $reference) {}

    /** Send a fresh code. Silently does nothing within the resend cooldown. */
    public function send(User $user, string $purpose): void
    {
        $key = $this->key($user, $purpose);

        if (Cache::has("{$key}:cooldown")) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($key, ['hash' => Hash::make($code), 'attempts' => 0], self::TTL_SECONDS);
        Cache::put("{$key}:cooldown", true, self::RESEND_AFTER_SECONDS);

        $to = $this->reference->userNumber($user);
        $message = $purpose === self::LOGIN
            ? "{$code} is your Homecare login code. It expires in 5 minutes. Do not share it."
            : "{$code} is your Homecare password reset code. It expires in 5 minutes. Do not share it.";

        if (! $to) {
            Log::warning('[OTP] User has no mobile number', ['user' => $user->username]);

            return;
        }

        if (! SendSms::configured()) {
            Log::info('[OTP] SMS not configured, code not sent', ['user' => $user->username]);

            // Lets developers sign in locally without Twilio.
            if (app()->isLocal()) {
                Log::debug("[OTP] {$purpose} code for {$user->username}: {$code}");
            }

            return;
        }

        SendSms::dispatch($to, $message);
    }

    /** Check a code and consume it on success. */
    public function verify(User $user, string $purpose, string $code): bool
    {
        $key = $this->key($user, $purpose);
        $entry = Cache::get($key);

        if (! is_array($entry)) {
            return false;
        }

        if (Hash::check($code, $entry['hash'])) {
            Cache::forget($key);

            return true;
        }

        $entry['attempts']++;
        $entry['attempts'] >= self::MAX_ATTEMPTS
            ? Cache::forget($key)
            : Cache::put($key, $entry, self::TTL_SECONDS);

        return false;
    }

    private function key(User $user, string $purpose): string
    {
        return "otp:{$purpose}:{$user->getKey()}";
    }
}
