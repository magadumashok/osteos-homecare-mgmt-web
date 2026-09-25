<?php

namespace App\Http\Requests\Clinical;

use App\Http\Requests\Rules;
use App\Models\AdministerMedication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One session of one medicine given. The witnessing guardian is an
 * existing `guardian_id`, or a new `guardian_name` with a signature `image`.
 */
class AdministerMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medicine_name' => ['required', 'string', 'max:190'],
            'date' => ['required', Rules::DATE],
            'medicine_type' => ['required', Rule::in(AdministerMedication::SESSIONS)],
            'medicine_date' => ['nullable', Rules::DATE],
            'medicine_time' => ['nullable', Rules::TIME],
            'guardian_id' => ['nullable', 'string', 'max:120'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'required_with:guardian_name', 'string'],
        ];
    }
}
