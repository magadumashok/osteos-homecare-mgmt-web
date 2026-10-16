<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

/**
 * Firebase Cloud Messaging to the mobile app. Each user has one device
 * token (`firebase_token`, saved by PUT account/firebase-token).
 *
 * `data` keys the app reads: `type` (what the push is about) and, for
 * calls, `videoRoomUrl`. FCM data values must be strings.
 */
class PushNotificationService
{
    public function configured(): bool
    {
        return filled(config('firebase.projects.'.config('firebase.default').'.credentials'));
    }

    /**
     * Queue a push to a user's device. Skipped (and logged) when the user
     * has no device registered or Firebase is not configured.
     *
     * @param  array<string, scalar|null>  $data
     */
    public function notify(?User $user, string $title, string $body, array $data = []): void
    {
        $token = $user?->firebase_token;

        if (! $token) {
            return;
        }

        if (! $this->configured()) {
            Log::info('[Push] Firebase not configured, notification skipped', ['type' => $data['type'] ?? null]);

            return;
        }

        SendPushNotification::dispatch($token, $title, $body, $data)->afterCommit();
    }

    /**
     * Send now. A token Firebase no longer accepts (app uninstalled, or
     * from another Firebase project) is removed from every account.
     *
     * @param  array<string, scalar|null>  $data
     *
     * @throws NotFound|SenderIdMismatch when the token is no longer valid
     */
    public function send(string $token, string $title, string $body, array $data = []): void
    {
        $message = CloudMessage::new()
            ->toToken($token)
            ->withNotification(Notification::create($title, $body))
            ->withData([...array_map(fn ($value) => (string) $value, $data), 'sender' => (string) config('app.name')])
            ->withHighestPossiblePriority()
            ->withDefaultSounds();

        try {
            Firebase::messaging()->send($message);
        } catch (NotFound|SenderIdMismatch $e) {
            $this->forget($token);

            throw $e;
        }
    }

    /** Stop sending to a device token, whichever accounts hold it. */
    public function forget(string $token): void
    {
        User::where('firebase_token', $token)->update(['firebase_token' => null]);
    }
}
