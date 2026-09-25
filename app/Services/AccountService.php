<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Str;

/** Things users do to their own account. */
class AccountService
{
    public function __construct(private readonly FileStorageService $files) {}

    public function updateFirebaseToken(User $user, ?string $token): void
    {
        $user->update(['firebase_token' => $token]);
    }

    /**
     * Store a doctor's signature (base64 PNG), printed on prescriptions.
     *
     * @return string the stored file name
     */
    public function saveSignature(User $user, string $image): string
    {
        $fileName = 'signature_'.Str::random(10).'.png';

        $user->update([
            'digitalsignature' => $this->files->storeBase64($image, "doctors/{$user->username}/digitalsignature", $fileName),
        ]);

        return $fileName;
    }

    public function submitFeedback(User $user, string $feedback): Feedback
    {
        return Feedback::create(['username' => $user->username, 'feedback' => $feedback]);
    }
}
