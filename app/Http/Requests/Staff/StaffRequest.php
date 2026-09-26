<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** Create (POST) or edit (PUT) a staff account. Roles are changed separately. */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'mobile' => [$creating ? 'required' : 'sometimes', 'string', Rules::MOBILE],
            'alternate_mobile' => ['nullable', 'string', Rules::MOBILE],
            'email' => ['nullable', 'email', 'max:190'],
            'age' => ['nullable', 'integer', 'min:14', 'max:100'],
            'identity_type' => ['nullable', 'string', 'max:60'],
            'identity_number' => ['nullable', 'string', 'max:60'],
            'temporary_address' => [$creating ? 'required' : 'sometimes', 'array'],
            'temporary_address.country' => [$creating ? 'required' : 'sometimes', 'string', 'max:10'],
            'temporary_address.region' => ['nullable', 'string', 'max:20'],
            'temporary_address.city' => ['nullable', 'string', 'max:120'],
            'temporary_address.address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'array'],
            'permanent_address.country' => ['nullable', 'string', 'max:10'],
            'permanent_address.region' => ['nullable', 'string', 'max:20'],
            'permanent_address.city' => ['nullable', 'string', 'max:120'],
            'permanent_address.address' => ['nullable', 'string', 'max:500'],
            'password' => [$creating ? 'required' : 'prohibited', 'string', 'min:8'],
            'role' => [$creating ? 'required' : 'prohibited', 'string', 'max:60'],
            'userID' => [$creating ? 'required' : 'prohibited', 'string', 'max:40', 'alpha_dash'],
        ];
    }
}
