<?php

namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;

/**
 * Sends one push notification through Firebase. Queued so a slow or
 * failing FCM call never fails the request that triggered it.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /** @param  array<string, scalar|null>  $data */
    public function __construct(
        public readonly string $token,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(PushNotificationService $push): void
    {
        try {
            $push->send($this->token, $this->title, $this->body, $this->data);
        } catch (NotFound|SenderIdMismatch) {
            // The device is gone and its token was cleared; retrying cannot help.
            Log::info('[Push] Device token no longer valid, removed', ['type' => $this->data['type'] ?? null]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('[Push] Not delivered', ['type' => $this->data['type'] ?? null, 'error' => $e->getMessage()]);
    }
}
