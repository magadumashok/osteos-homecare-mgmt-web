<?php

namespace App\Http\Requests\Clinical;

use App\Http\Requests\Rules;
use App\Models\PatientVitalSign;
use Illuminate\Foundation\Http\FormRequest;

class VitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'date' => [$required, Rules::DATE],
            'time' => [$required, Rules::TIME],
            ...array_fill_keys(PatientVitalSign::MEASUREMENTS, ['nullable', 'string', 'max:30']),
        ];
    }
}
