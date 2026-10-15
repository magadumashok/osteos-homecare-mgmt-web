<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * MSG91Service
 *
 * Drop-in replacement for PlivoService.
 * Same sendOtp() and verifyOtp() signatures — no changes needed
 * in NewAuthController or anywhere else.
 *
 * .env variables required:
 *   MSG91_AUTH_KEY=your_auth_key_here
 *   MSG91_SENDER_ID=Osteos
 *   MSG91_TEMPLATE_ID=69f1b3bd6ddc2865fb0db612
 */
class MSG91Service
{
    private string $authKey;
    private string $senderId;
    private string $templateId;

    private const OTP_LENGTH   = 6;
    private const OTP_TTL      = 300; // 5 minutes in seconds
    private const MAX_ATTEMPTS = 3;
    private const LOCK_TTL     = 300; // lock for 5 minutes after max attempts

    private const MSG91_API_URL = 'https://control.msg91.com/api/v5/otp';

    public function __construct()
    {
        $this->authKey    = config('services.msg91.auth_key');
        $this->senderId   = config('services.msg91.sender_id', 'Osteos');
        $this->templateId = config('services.msg91.template_id');
    }

    // =========================================================================
    // Send OTP
    // =========================================================================
    public function sendOtp(string $mobile): array
    {
        // Locked after too many wrong attempts
        if (Cache::has("otp_locked:{$mobile}")) {
            return [
                'success' => false,
                'message' => 'Too many attempts. Please try again after 5 minutes.',
            ];
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(100000, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        // Store in cache with 5 min TTL
        Cache::put("otp:{$mobile}", $otp, self::OTP_TTL);
        Cache::put("otp_attempts:{$mobile}", 0, self::OTP_TTL);

        try {
            $response = Http::withHeaders([
                'authkey'      => $this->authKey,
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->post(self::MSG91_API_URL, [
                'mobile'      => '91' . $mobile, // MSG91 requires country code prefix
                'authkey'     => $this->authKey,
                'template_id' => $this->templateId,
                'otp'         => $otp,
                'otp_length'  => self::OTP_LENGTH,
                'otp_expiry'  => self::OTP_TTL / 60, // minutes
                'sender'      => $this->senderId,
            ]);

            $body = $response->json();

            Log::info("[MSG91Service] sendOtp response for {$mobile}", $body ?? []);

            if ($response->successful() && isset($body['type']) && $body['type'] === 'success') {
                return ['success' => true, 'message' => 'OTP sent successfully.'];
            }

            $errorMsg = $body['message'] ?? $body['error'] ?? 'Failed to send OTP.';
            Log::warning("[MSG91Service] sendOtp failed for {$mobile}: {$errorMsg}");

            return ['success' => false, 'message' => $errorMsg];

        } catch (\Exception $e) {
            Log::error("[MSG91Service] Exception for {$mobile}: " . $e->getMessage());
            return ['success' => false, 'message' => 'SMS service unavailable. Please try again.'];
        }
    }

    // =========================================================================
    // Verify OTP (against cache — no extra MSG91 API call needed)
    // =========================================================================
    public function verifyOtp(string $mobile, string $otp): array
    {
        if (Cache::has("otp_locked:{$mobile}")) {
            return [
                'success' => false,
                'message' => 'Too many attempts. Please try again after 5 minutes.',
            ];
        }

        $cachedOtp = Cache::get("otp:{$mobile}");

        if (!$cachedOtp) {
            return [
                'success' => false,
                'message' => 'OTP expired or not requested. Please request a new OTP.',
            ];
        }

        if ($cachedOtp !== $otp) {
            $attempts = Cache::increment("otp_attempts:{$mobile}");

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::put("otp_locked:{$mobile}", true, self::LOCK_TTL);
                Cache::forget("otp:{$mobile}");
                Cache::forget("otp_attempts:{$mobile}");

                return [
                    'success' => false,
                    'message' => 'Too many incorrect attempts. Please try again after 5 minutes.',
                ];
            }

            $remaining = self::MAX_ATTEMPTS - $attempts;
            return [
                'success' => false,
                'message' => "Incorrect OTP. {$remaining} attempt(s) remaining.",
            ];
        }

        // ✅ Correct — clear cache
        Cache::forget("otp:{$mobile}");
        Cache::forget("otp_attempts:{$mobile}");

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    }
}
