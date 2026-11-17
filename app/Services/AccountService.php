<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Things users do to their own account. */
class AccountService
{
    public function __construct(
        private readonly FileStorageService $files,
        private readonly StaffProfilePdfService $profilePdf,
    ) {}

    /**
     * One device per account. A device that changes accounts without
     * signing out (e.g. offline) stops getting the previous account's pushes.
     */
    public function updateFirebaseToken(User $user, ?string $token): void
    {
        // The app sends its token on every sign-in and launch; usually unchanged.
        if ($user->firebase_token === $token) {
            return;
        }

        if ($token) {
            User::where('firebase_token', $token)->where('_id', '!=', $user->getKey())->update(['firebase_token' => null]);
        }

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

    /** The user's profile photo, shown on their profile and in the staff directory. */
    public function saveProfileImage(User $user, UploadedFile $image): string
    {
        $user->update([
            'profile_image' => $this->files->storeUpload($image, "users/{$user->username}/profile"),
        ]);
        $this->profilePdf->refresh($user);

        return $user->profile_image;
    }

    /** The user's own identity documents (hidden from every list; see User::PRIVATE_FIELDS). */
    public function documents(User $user): array
    {
        return [
            'profile_image' => $user->profile_image,
            'aadhaar_card' => $user->aadhaar_card,
            'police_verification' => $user->police_verification,
        ];
    }

    /** The user's own Aadhaar card (a photo of it), replacing any earlier one. */
    public function saveAadhaarCard(User $user, UploadedFile $file): array
    {
        $user->update(['aadhaar_card' => $this->files->storeUpload($file, "users/{$user->username}/documents")]);
        $this->profilePdf->refresh($user);

        return $this->documents($user);
    }

    public function submitFeedback(User $user, string $feedback): Feedback
    {
        return Feedback::create(['username' => $user->username, 'feedback' => $feedback]);
    }
}
