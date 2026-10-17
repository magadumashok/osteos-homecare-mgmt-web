<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends one-time codes by SMS through MSG91's OTP API (the same request
 * the EMR app uses). Codes are generated and checked by OtpService; this
 * only delivers them, so MSG91's own verify endpoint is not used.
 *
 * .env:
 *   MSG91_AUTH_KEY=...
 *   MSG91_SENDER_ID=Osteos
 *   MSG91_TEMPLATE_ID=...            (approved OTP template, with the ##OTP## variable)
 *   MSG91_DEFAULT_COUNTRY_CODE=91    (for numbers saved without a country code)
 */
class MSG91Service
{
    private const API_URL = 'https://control.msg91.com/api/v5/otp';

    public function configured(): bool
    {
        return filled(config('services.msg91.auth_key')) && filled(config('services.msg91.template_id'));
    }

    /**
     * @param  string  $mobile  international (+919876543210) or local (9876543210)
     * @param  int  $expiryMinutes  shown in the template when it has an expiry variable
     *
     * @throws RuntimeException when MSG91 does not accept the message
     */
    public function sendOtp(string $mobile, string $otp, int $expiryMinutes = 5): void
    {
        $authKey = (string) config('services.msg91.auth_key');

        $response = Http::timeout(15)
            ->withHeaders(['authkey' => $authKey, 'accept' => 'application/json'])
            ->post(self::API_URL, [
                'mobile' => $this->msisdn($mobile),
                'authkey' => $authKey,
                'template_id' => config('services.msg91.template_id'),
                'otp' => $otp,
                'otp_length' => strlen($otp),
                'otp_expiry' => $expiryMinutes,
                'sender' => config('services.msg91.sender_id') ?: 'Osteos',
            ]);

        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['type'] ?? null) !== 'success') {
            throw new RuntimeException('MSG91: '.($body['message'] ?? $body['error'] ?? "HTTP {$response->status()}"));
        }
    }

    /** MSG91 wants digits only, country code first, no "+". */
    private function msisdn(string $mobile): string
    {
        $international = str_starts_with(trim($mobile), '+');
        $digits = preg_replace('/\D/', '', $mobile);

        if ($international) {
            return $digits;
        }

        $digits = ltrim($digits, '0');
        $countryCode = (string) config('services.msg91.default_country_code', '91');

        return strlen($digits) > 10 && str_starts_with($digits, $countryCode) ? $digits : $countryCode.$digits;
    }
}
