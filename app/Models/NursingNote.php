<?php

namespace App\Models;

/** A nurse's text, audio or image note. Note types are listed in NoteService. */
class NursingNote extends TenantModel
{
    protected $table = 'nursing_notes';

    protected $fillable = [
        'nurse_id', 'nurse_name', 'patient_id', 'date', 'time',
        'notes_type', 'notes', 'audio_notes', 'image_notes',
    ];
}
