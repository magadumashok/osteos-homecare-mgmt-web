<?php

namespace App\Services;

use App\Models\User;
use App\Support\SimplePdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One PDF per staff member, sent to patients with the duty WhatsApp: their
 * name, role, experience, profile photo and Aadhaar card (not the police
 * verification). Stored on S3 with the other documents; the user keeps
 *
 *   staff_profile_pdf       URL of the current PDF (null when not possible)
 *   staff_profile_pdf_hash  what it was built from, to know when it is stale
 *
 * It is built only when both `profile_image` and `aadhaar_card` are set,
 * and rebuilt whenever either of them, the name or the experience changes
 * (on registration, admin edits and the staff member's own uploads). A
 * failure is logged and never stops the upload that caused it.
 */
class StaffProfilePdfService
{
    public function __construct(
        private readonly FileStorageService $files,
        private readonly TenantService $tenants,
    ) {}

    /** Build (or drop) the PDF if what it shows has changed. Returns its URL, or null. */
    public function refresh(User $user): ?string
    {
        try {
            if (! $this->hasSources($user)) {
                if ($user->staff_profile_pdf || $user->staff_profile_pdf_hash) {
                    $user->update(['staff_profile_pdf' => null, 'staff_profile_pdf_hash' => null]);
                }

                return null;
            }

            $hash = $this->fingerprint($user);
            if ($user->staff_profile_pdf && $user->staff_profile_pdf_hash === $hash) {
                return $user->staff_profile_pdf;
            }

            $pdf = $this->build($user);
            if ($pdf === null) {
                return null;
            }

            // "<homecare>/users/<username>/documents/user-profile-<time>.pdf": a new
            // name each time, so a CDN or WhatsApp never serves the old copy.
            $url = $this->files->put($pdf, "users/{$user->username}/documents", 'user-profile-'.now()->format('YmdHis').'.pdf');
            $user->update(['staff_profile_pdf' => $url, 'staff_profile_pdf_hash' => $hash]);

            return $url;
        } catch (\Throwable $e) {
            Log::warning('[StaffProfilePdf] Not built', ['user' => $user->username, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The PDF's path under the documents URL, for a WhatsApp template whose
     * document header is "<documents url>/{{n}}". Null unless the staff
     * member has both a profile photo and an Aadhaar card.
     */
    public function forWhatsApp(?User $user): ?string
    {
        if (! $user || ! $this->hasSources($user)) {
            return null;
        }

        return $this->files->relativeUrl($this->refresh($user));
    }

    public function hasSources(User $user): bool
    {
        return filled($user->profile_image) && filled($user->aadhaar_card);
    }

    private function fingerprint(User $user): string
    {
        return md5(json_encode([
            $user->profile_image, $user->aadhaar_card, $user->displayName(), $user->experience, $user->roles,
        ]));
    }

    private function build(User $user): ?string
    {
        $photo = $this->jpeg($this->files->read($user->profile_image), 'profile photo', $user);
        $aadhaar = $this->jpeg($this->files->read($user->aadhaar_card), 'Aadhaar card', $user);
        if (! $photo || ! $aadhaar) {
            return null;
        }

        $teal = [26, 127, 142];
        $grey = [110, 120, 130];
        $margin = 50;
        $pdf = (new SimplePdf)->addPage();

        $pdf->rect(0, 0, SimplePdf::WIDTH, 78, $teal);
        $pdf->text($margin, 22, $this->tenants->displayName() ?: 'Homecare', 18, true, [255, 255, 255]);
        $pdf->text($margin, 48, 'Your care professional', 11, false, [255, 255, 255]);

        // Photo on the left, details on the right.
        $top = 108;
        $photoHeight = $pdf->jpeg($photo['data'], $photo['width'], $photo['height'], $margin, $top, 150, 190);

        $x = 230;
        $width = SimplePdf::WIDTH - $x - $margin;
        $pdf->text($x, $top, 'Name', 10, false, $grey);
        $y = $pdf->paragraph($x, $top + 15, $width, $user->displayName() ?: $user->username, 16, true);

        $role = collect($user->roles ?? [])->map(fn ($r) => Str::headline($r))->implode(', ');
        if ($role !== '') {
            $pdf->text($x, $y + 10, 'Role', 10, false, $grey);
            $y = $pdf->paragraph($x, $y + 25, $width, $role, 12);
        }

        $pdf->text($x, $y + 10, 'Experience', 10, false, $grey);
        $y = $pdf->paragraph($x, $y + 25, $width, filled($user->experience) ? (string) $user->experience : 'Not provided', 12);

        // Aadhaar card across the page below.
        $y = max($top + $photoHeight, $y) + 34;
        $pdf->text($margin, $y, 'Aadhaar card', 13, true);
        $pdf->jpeg($aadhaar['data'], $aadhaar['width'], $aadhaar['height'], $margin, $y + 24, SimplePdf::WIDTH - 2 * $margin, SimplePdf::HEIGHT - $y - 24 - 50);

        $pdf->text($margin, SimplePdf::HEIGHT - 34, 'Shared for verification of your care professional only.', 8, false, $grey);

        return $pdf->output();
    }

    /**
     * Any image GD reads, as an RGB JPEG (transparency on white, at most
     * 1600 px on the longer side). Null (and logged) for a PDF or bad file.
     *
     * @return array{data: string, width: int, height: int}|null
     */
    private function jpeg(?string $bytes, string $what, User $user): ?array
    {
        $image = $bytes && ! str_starts_with($bytes, '%PDF') ? @imagecreatefromstring($bytes) : false;
        if (! $image) {
            Log::info("[StaffProfilePdf] The {$what} is not an image, PDF not built", ['user' => $user->username]);

            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1600 / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $data = (string) ob_get_clean();

        return ['data' => $data, 'width' => $w, 'height' => $h];
    }
}
