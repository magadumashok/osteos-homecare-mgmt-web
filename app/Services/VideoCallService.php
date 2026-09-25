<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

/** Push notification that rings a colleague's app into a video room. */
class VideoCallService
{
    public function __construct(private readonly UserDirectoryService $users) {}

    /** Call a colleague in the same homecare by username. */
    public function callUser(string $username, User $caller, string $roomUrl): void
    {
        $recipient = $this->users->find($username);

        if (! $recipient?->firebase_token) {
            throw ValidationException::withMessages(['username' => 'This person cannot receive calls right now.']);
        }

        $this->send($recipient->firebase_token, 'Incoming video call', "{$caller->name} is calling you.", $roomUrl);
    }

    /** Send to a raw device token (the legacy endpoint's contract). */
    public function send(string $token, string $title, string $body, string $roomUrl): void
    {
        $message = CloudMessage::new()
            ->toToken($token)
            ->withNotification(Notification::create($title, $body))
            ->withData(['videoRoomUrl' => $roomUrl, 'sender' => config('app.name')]);

        Firebase::messaging()->send($message);
    }
}
