<?php

namespace App\Http\Requests\Leads;

use App\Http\Requests\Rules;
use App\Models\PatientLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST) or update (PUT) a lead. On create, `mobile` is local and `country` required. */
class PatientLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'patient_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'mobile' => [$creating ? 'required' : 'sometimes', 'string', Rules::MOBILE],
            'country' => [$creating ? 'required' : 'sometimes', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:190'],
            'staff' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', Rules::DATE],
            'time' => ['nullable', Rules::TIME],
            'followup' => ['nullable', 'string', Rule::notIn([PatientLead::FOLLOWUP_ASSESSMENT_DONE])],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
