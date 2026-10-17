<?php

namespace App\Jobs;

use App\Services\MSG91Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Sends one one-time code by SMS through MSG91. */
class SendOtpSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(
        public readonly string $to,
        public readonly string $code,
    ) {}

    public function handle(MSG91Service $msg91): void
    {
        $msg91->sendOtp($this->to, $this->code);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('[SMS] OTP not delivered', ['to' => $this->to, 'error' => $e->getMessage()]);
    }
}
