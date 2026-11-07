<?php

namespace App\Services;

use App\Jobs\SendWhatsAppTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Queues WhatsApp template messages. Template names are keys of
 * `homecare.twilio.templates`; variables are the template's placeholders
 * in order. Messages are skipped (and logged) when Twilio is not
 * configured or the recipient has no number.
 *
 * A message that cannot be sent is logged and never fails the caller: with
 * QUEUE_CONNECTION=sync the job runs inside the request, after the duty or
 * patient is saved.
 */
class WhatsAppService
{
    /** Stands in for a blank value: Twilio refuses an empty variable (error 21656). */
    public const BLANK = 'Not provided';

    /** A template variable as WhatsApp accepts it: never empty, on one line. */
    public static function variable(mixed $value): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' ? self::BLANK : $text;
    }

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
            $numbered[(string) ($i + 1)] = self::variable($value);
        }

        try {
            SendWhatsAppTemplate::dispatch($to, $contentSid, $numbered)->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Template not sent', ['template' => $template, 'error' => $e->getMessage()]);
        }
    }

    public function configured(): bool
    {
        $config = config('homecare.twilio');

        return ! empty($config['sid']) && ! empty($config['token']) && ! empty($config['whatsapp_from']);
    }
}
