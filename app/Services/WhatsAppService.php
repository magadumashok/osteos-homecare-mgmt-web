<?php

namespace App\Services;

use App\Jobs\SendWhatsAppTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Queues WhatsApp template messages. Template names are keys of
 * `homecare.twilio.templates`; variables are the template's placeholders
 * in order. Messages are skipped (and logged) when Twilio is not
 * configured or the recipient has no number.
 */
class WhatsAppService
{
    /** @param  list<scalar|null>  $variables */
    public function send(?string $to, string $template, array $variables): void
    {
        $to = trim((string) $to);
        $contentSid = config("homecare.twilio.templates.{$template}");

        if ($to === '' || ! $contentSid) {
            return;
        }

        if (! $this->configured()) {
            Log::info('[WhatsApp] Twilio not configured, message skipped', ['template' => $template]);

            return;
        }

        // Twilio numbers placeholders from 1.
        $numbered = [];
        foreach (array_values($variables) as $i => $value) {
            $numbered[(string) ($i + 1)] = (string) $value;
        }

        SendWhatsAppTemplate::dispatch($to, $contentSid, $numbered)->afterCommit();
    }

    public function configured(): bool
    {
        $config = config('homecare.twilio');

        return ! empty($config['sid']) && ! empty($config['token']) && ! empty($config['whatsapp_from']);
    }
}
