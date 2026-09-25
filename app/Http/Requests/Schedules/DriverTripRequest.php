<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

class DriverTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'string'],
            'staff_id' => ['required', 'string'],
            'address' => ['nullable', 'string', 'max:500'],
            'date' => ['required', Rules::DATE],
            'end_date' => ['required', Rules::DATE, 'after_or_equal:date'],
            'start_time' => ['required', Rules::TIME],
            'end_time' => ['required', Rules::TIME],
        ];
    }
}
