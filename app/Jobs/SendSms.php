<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Sends one plain SMS through Twilio, from TWILIO_SMS_FROM or, when that is
 * not set, through the messaging service. Used for one-time codes.
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(
        public readonly string $to,
        public readonly string $body,
    ) {}

    public static function configured(): bool
    {
        $config = config('homecare.twilio');

        return ! empty($config['sid']) && ! empty($config['token'])
            && (! empty($config['sms_from']) || ! empty($config['messaging_service_sid']));
    }

    public function handle(): void
    {
        $config = config('homecare.twilio');
        $client = new Client($config['sid'], $config['token']);

        $client->messages->create($this->to, [
            'body' => $this->body,
            ...(! empty($config['sms_from'])
                ? ['from' => $config['sms_from']]
                : ['messagingServiceSid' => $config['messaging_service_sid']]),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('[SMS] Not delivered', ['to' => $this->to, 'error' => $e->getMessage()]);
    }
}
