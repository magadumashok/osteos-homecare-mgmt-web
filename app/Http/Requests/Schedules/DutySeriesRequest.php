<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\Rules;
use App\Models\StaffDutySchedule;
use Illuminate\Foundation\Http\FormRequest;

/** The same shift on several date ranges (one duty per day, up to about six months). */
class DutySeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'string'],
            'role' => ['required', 'string', 'max:60'],
            'patient_id' => ['required', 'string'],
            'start_time' => ['required', Rules::TIME],
            'end_time' => ['required', Rules::TIME],
            'schedule_hour' => ['nullable'],
            'buffer_time' => ['nullable', Rules::TIME],
            'service' => ['nullable', 'string', 'max:120'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'ranges' => ['required', 'array', 'min:1', 'max:'.StaffDutySchedule::MAX_SERIES],
            'ranges.*.start_date' => ['required', Rules::DATE],
            'ranges.*.end_date' => ['required', Rules::DATE],
        ];
    }
}
