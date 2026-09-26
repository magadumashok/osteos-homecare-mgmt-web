<?php

namespace App\Services;

use App\Models\NursingNote;
use App\Models\StaffNote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Nursing notes (nurses) and staff notes (any role). Each note is text,
 * a recorded audio clip (base64 MP4 from the app) or a photo.
 */
class NoteService
{
    public const TEXT = 'text_notes';

    public const AUDIO = 'audio_notes';

    public const IMAGE = 'image_notes';

    public const TYPES = [self::TEXT, self::AUDIO, self::IMAGE];

    /** `nursing` → nursing_notes (nurse_id/nurse_name), `staff` → staff_notes (staff_id/staff_name/staff_role). */
    public const KINDS = ['nursing' => NursingNote::class, 'staff' => StaffNote::class];

    public function __construct(
        private readonly FileStorageService $files,
        private readonly UserDirectoryService $users,
    ) {}

    /**
     * A text note, an audio note (`audio`, base64), or both in one record
     * as the legacy app allowed (the note is then typed as audio).
     */
    public function store(string $kind, array $data, User $author): NursingNote|StaffNote
    {
        $attributes = [...$this->authorFields($kind, $data, $author), ...$this->when($data)];

        if (filled($data['notes'] ?? null)) {
            $attributes['notes'] = $data['notes'];
            $attributes['notes_type'] = self::TEXT;
        }

        if (filled($data['audio'] ?? null)) {
            $authorId = $attributes[$kind === 'nursing' ? 'nurse_id' : 'staff_id'];
            $name = "{$data['patient_id']}_{$authorId}_{$attributes['date']}_".str_replace(':', '', (string) $attributes['time']).'.mp4';

            $attributes['audio_notes'] = $this->files->storeBase64(
                $data['audio'], "patients/{$data['patient_id']}/audio_notes/{$attributes['date']}", $name,
            );
            $attributes['notes_type'] = self::AUDIO;
        }

        return $this->model($kind)::create($attributes);
    }

    public function storeImage(string $kind, array $data, UploadedFile $image, User $author): NursingNote|StaffNote
    {
        $attributes = [...$this->authorFields($kind, $data, $author), ...$this->when($data)];
        $folder = $kind === 'nursing' ? 'nurse_notes_images' : 'staff_notes_images';

        return $this->model($kind)::create([
            ...$attributes,
            'image_notes' => $this->files->storeUpload($image, "patients/{$data['patient_id']}/{$folder}/{$attributes['date']}"),
            'notes_type' => self::IMAGE,
        ]);
    }

    /** Today's notes of one type for a patient (what the app shows on a visit). */
    public function today(string $kind, string $patientId, string $type): Collection
    {
        return $this->on($kind, $patientId, Date::today()->format('Y-m-d'), $type);
    }

    public function on(string $kind, string $patientId, ?string $date, string $type): Collection
    {
        return $this->model($kind)::where('patient_id', $patientId)
            ->when($date, fn ($q) => $q->where('date', $date))
            ->where('notes_type', $type)
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get();
    }

    /** Staff notes with the author's current name and role label (admin view). */
    public function staffNotesWithAuthors(string $patientId, ?string $date, string $type): Collection
    {
        $notes = $this->on('staff', $patientId, $date, $type);
        $authors = $this->users->byUsername($notes->pluck('staff_id'));

        return $notes->each(function (StaffNote $note) use ($authors) {
            if ($author = $authors->get($note->staff_id)) {
                $note->nurse_name = $author->displayName();
                $note->role_name = ucfirst((string) $author->primaryRole());
            }
        });
    }

    private function authorFields(string $kind, array $data, User $author): array
    {
        if ($kind === 'nursing') {
            return [
                'patient_id' => $data['patient_id'],
                'nurse_id' => $data['nurse_id'] ?? $author->username,
                'nurse_name' => $data['nurse_name'] ?? $author->displayName(),
            ];
        }

        return [
            'patient_id' => $data['patient_id'],
            'staff_id' => $data['staff_id'] ?? $author->username,
            'staff_name' => $data['staff_name'] ?? $author->displayName(),
            'staff_role' => $data['staff_role'] ?? $author->primaryRole(),
        ];
    }

    private function when(array $data): array
    {
        return [
            'date' => $data['date'] ?? Date::today()->format('Y-m-d'),
            'time' => $data['time'] ?? Date::now()->format('H:i:s'),
        ];
    }

    /** @return class-string<NursingNote|StaffNote> */
    private function model(string $kind): string
    {
        return self::KINDS[$kind] ?? throw new \InvalidArgumentException("Unknown note kind '{$kind}'.");
    }
}
