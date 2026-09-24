<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterHomecareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'owner' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'country' => ['required', 'string', 'max:10'],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'center_id' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter the mobile number as digits only, without the country code.',
        ];
    }
}
