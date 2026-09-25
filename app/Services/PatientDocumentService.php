<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\StaffDutySchedule;
use Illuminate\Http\UploadedFile;

/**
 * Files attached to a patient: general documents (`images`), ID scans
 * (`emirates_id`) and specimen photos (`specimen_images`).
 *
 * URLs are appended with $push / removed with $pull, so two uploads at the
 * same moment no longer overwrite each other (the legacy app read the
 * array, appended in PHP and saved it back).
 */
class PatientDocumentService
{
    /** field => storage folder under patients/<id>/ */
    public const FOLDERS = [
        'images' => 'documents',
        'emirates_id' => 'ID',
        'specimen_images' => 'Specimen',
    ];

    public function __construct(private readonly FileStorageService $files) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @return list<string> the stored URLs
     */
    public function add(Patient $patient, string $field, array $uploads): array
    {
        $urls = $this->files->storeUploads($uploads, $this->folder($patient, $field));

        if ($urls !== []) {
            Patient::where('username', $patient->username)->push($field, $urls);
        }

        return $urls;
    }

    /**
     * Specimen photos taken during a duty, tagged with lab details, added
     * to both the patient and the duty.
     *
     * @param  list<UploadedFile>  $uploads
     */
    public function addSpecimens(Patient $patient, ?StaffDutySchedule $duty, array $uploads, ?string $labDetails): array
    {
        $entries = array_map(
            fn (string $url) => ['lab_details' => $labDetails, 'image_path' => $url],
            $this->files->storeUploads($uploads, $this->folder($patient, 'specimen_images')),
        );

        if ($entries !== []) {
            Patient::where('username', $patient->username)->push('specimen_images', $entries);
            if ($duty) {
                StaffDutySchedule::where('_id', $duty->getKey())->push('specimen_images', $entries);
            }
        }

        return $entries;
    }

    /** Detach a file from the patient. The stored object is kept. */
    public function remove(Patient $patient, string $url, string $field = 'images'): void
    {
        Patient::where('username', $patient->username)->pull($field, $url);
    }

    private function folder(Patient $patient, string $field): string
    {
        $folder = self::FOLDERS[$field] ?? throw new \InvalidArgumentException("Unknown document field '{$field}'.");

        return "patients/{$patient->username}/{$folder}";
    }
}
