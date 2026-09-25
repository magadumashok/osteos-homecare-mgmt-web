<?php

namespace App\Models;

/** A text, audio or image note by any staff role. Note types are listed in NoteService. */
class StaffNote extends TenantModel
{
    protected $table = 'staff_notes';

    protected $fillable = [
        'staff_id', 'staff_name', 'staff_role', 'patient_id', 'date', 'time',
        'notes_type', 'notes', 'audio_notes', 'image_notes',
    ];
}
