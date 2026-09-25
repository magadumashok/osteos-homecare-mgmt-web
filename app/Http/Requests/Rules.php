<?php

namespace App\Http\Requests;

/**
 * Shared rule fragments. Dates and times are stored as the strings the
 * legacy clients used ("2025-01-31", "14:30"), so they are validated as
 * formats, not parsed.
 */
final class Rules
{
    public const DATE = 'date_format:Y-m-d';

    /** "HH:MM", optionally with seconds. Also used for durations such as buffer_time. */
    public const TIME = 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/';

    public const MOBILE = 'regex:/^\+?[0-9]{6,15}$/';

    public static function upload(): string
    {
        return 'max:'.config('homecare.documents.max_upload_kb');
    }
}
