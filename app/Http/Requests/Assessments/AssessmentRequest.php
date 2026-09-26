<?php

namespace App\Http\Requests\Assessments;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Record (POST) or update (PUT) an initial assessment. The clinical
 * sections are free-form objects filled in by the assessment form.
 */
class AssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'lead_id' => [$creating ? 'required' : 'prohibited'],
            'patient_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'identity_number' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', Rules::DATE],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'gender' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'physician' => ['nullable', 'string', 'max:120'],
            'diagnosis' => ['nullable'],
            'diagnosis_name' => ['nullable'],
            'physical_assessment' => ['nullable'],
            'daily_activities' => ['nullable'],
            'equipment' => ['nullable', 'array'],
            'medications' => ['nullable'],
            'care_plan' => ['nullable', 'array'],
            'nursing' => ['nullable'],
            'physiotherapy' => ['nullable'],
            'physiotherapy_details' => ['nullable', 'array'],
            'physiotherapy_treatment_plan' => ['nullable', 'array'],
            'doctor' => ['nullable'],
            'invoice_details' => ['nullable', 'array'],
            'document_type' => ['nullable', 'string', 'max:60'],
            'document' => ['nullable', 'file', Rules::upload()],
        ];
    }
}
