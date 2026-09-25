<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** The same duty on several date ranges. */
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
            'ranges' => ['required', 'array', 'min:1', 'max:62'],
            'ranges.*.start_date' => ['required', Rules::DATE],
            'ranges.*.end_date' => ['required', Rules::DATE],
        ];
    }
}
