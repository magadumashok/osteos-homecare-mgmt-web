<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\NoteRequest;
use App\Http\Requests\Rules;
use App\Services\NoteService;
use App\Services\PatientService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** `{kind}` is "nursing" (nurses' notes) or "staff" (any role's notes). */
class NoteController extends Controller
{
    use ResponseTrait;

    public function __construct(
        private readonly NoteService $notes,
        private readonly PatientService $patients,
    ) {}

    /** ?type=text_notes|audio_notes|image_notes (default text), ?date=Y-m-d (default: all dates). */
    public function index(Request $request, string $username, string $kind): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(NoteService::TYPES)],
            'date' => ['nullable', Rules::DATE],
        ]);
        $patientId = $this->patients->findOrFail($username)->username;
        $type = $filters['type'] ?? NoteService::TEXT;

        $notes = $kind === 'staff'
            ? $this->notes->staffNotesWithAuthors($patientId, $filters['date'] ?? null, $type)
            : $this->notes->on($kind, $patientId, $filters['date'] ?? null, $type);

        return $this->response(true, '', 200, $notes);
    }

    public function store(NoteRequest $request, string $username, string $kind): JsonResponse
    {
        $data = [...$request->safe()->except('image'), 'patient_id' => $this->patients->findOrFail($username)->username];

        $note = $request->hasFile('image')
            ? $this->notes->storeImage($kind, $data, $request->file('image'), $request->user())
            : $this->notes->store($kind, $data, $request->user());

        return $this->response(true, 'Note saved.', 201, $note);
    }
}
