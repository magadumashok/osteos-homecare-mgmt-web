<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

class ShortVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'string'],
            'patient_name' => ['required', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'country' => ['nullable', 'string', 'max:60'],
            'patient_address' => ['nullable', 'string', 'max:500'],
            'date' => ['required', Rules::DATE],
            'time' => ['required', Rules::TIME],
            'visit_purpose' => ['nullable', 'string', 'max:500'],
            'visit_fees' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
