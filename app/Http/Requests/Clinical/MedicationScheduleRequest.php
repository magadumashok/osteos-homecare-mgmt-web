<?php

namespace App\Http\Requests\Clinical;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

class MedicationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medicine_name' => ['required', 'string', 'max:190'],
            'dosage' => ['nullable', 'string', 'max:60'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'frequency' => ['nullable'],
            'before_or_after_food' => ['nullable', 'string', 'max:30'],
            'date' => ['required', Rules::DATE],
            'time' => ['nullable', Rules::TIME],
        ];
    }
}
