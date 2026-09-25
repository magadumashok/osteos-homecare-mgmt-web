<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Sends one approved WhatsApp template through Twilio. Queued so a slow or
 * failing Twilio call never fails the request that triggered it (the
 * legacy app sent inline, so one bad number broke saving a schedule).
 */
class SendWhatsAppTemplate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** @param  array<string, string>  $variables  template placeholders "1", "2", ... */
    public function __construct(
        public readonly string $to,
        public readonly string $contentSid,
        public readonly array $variables,
    ) {}

    public function handle(): void
    {
        $config = config('homecare.twilio');
        $client = new Client($config['sid'], $config['token']);

        // Sent from the WhatsApp number only. The legacy app also passed
        // "MessagingServiceSID", but the SDK ignores that spelling, so the
        // messaging service was never actually used.
        $client->messages->create('whatsapp:'.$this->to, [
            'from' => 'whatsapp:'.$config['whatsapp_from'],
            'contentSid' => $this->contentSid,
            'contentVariables' => json_encode($this->variables),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('[WhatsApp] Template not delivered', [
            'to' => $this->to,
            'template' => $this->contentSid,
            'error' => $e->getMessage(),
        ]);
    }
}
