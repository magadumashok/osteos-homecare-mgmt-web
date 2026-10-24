<?php

namespace App\Http\Requests\Patients;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** Register (POST) or edit (PUT) a patient. On register, `mobile` is local. */
class PatientRequest extends FormRequest
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
            'mobile' => ['nullable', 'string', Rules::MOBILE],
            'email' => ['nullable', 'email', 'max:190'],
            'gender' => ['nullable', 'string', 'max:20'],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'date_of_birth' => ['nullable', Rules::DATE],
            'id_no' => ['nullable', 'string', 'max:60'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'insurance_name' => ['nullable', 'string', 'max:120'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'country' => [$creating ? 'required' : 'sometimes', 'string', 'max:60'],
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'source_of_contact' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:60'],
            'service' => ['nullable', 'string', 'max:120'],
            'service_package' => ['nullable', 'string', 'max:120'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'date' => ['nullable', Rules::DATE],
            'time' => ['nullable', Rules::TIME],
        ];
    }
}
