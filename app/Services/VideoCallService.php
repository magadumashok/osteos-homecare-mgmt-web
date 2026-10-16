<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;

/** Push notification that rings a colleague's app into a video room. */
class VideoCallService
{
    public function __construct(
        private readonly UserDirectoryService $users,
        private readonly PushNotificationService $push,
    ) {}

    /**
     * Call a colleague in the same homecare by username. Sent right away
     * (not queued) so the caller learns whether the phone was reached.
     */
    public function callUser(string $username, User $caller, string $roomUrl): void
    {
        $recipient = $this->users->find($username);
        $unreachable = ValidationException::withMessages(['username' => 'This person cannot receive calls right now.']);

        if (! $recipient?->firebase_token) {
            throw $unreachable;
        }

        if (! $this->push->configured()) {
            abort(503, 'Video calls are not set up on the server.');
        }

        try {
            $this->send($recipient->firebase_token, 'Incoming video call', "{$caller->displayName()} is calling you.", $roomUrl);
        } catch (NotFound|SenderIdMismatch) {
            throw $unreachable;
        } catch (\Throwable $e) {
            Log::error('[VideoCall] FCM send failed', ['error' => $e->getMessage()]);
            abort(503, 'The call could not be placed. Please try again.');
        }
    }

    /** Send to a raw device token (the legacy endpoint's contract). */
    public function send(string $token, string $title, string $body, string $roomUrl): void
    {
        $this->push->send($token, $title, $body, ['type' => 'video_call', 'videoRoomUrl' => $roomUrl]);
    }
}
