<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** Schedule (POST) or reschedule (PUT) a staff duty. `driver` is the driver's name. */
class DutyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'staff_id' => [$required, 'string'],
            'role' => [$required, 'string', 'max:60'],
            'patient_id' => [$this->isMethod('post') ? 'required' : 'prohibited', 'string'],
            'driver' => ['nullable', 'string', 'max:120'],
            'date' => [$required, Rules::DATE],
            'end_date' => [$required, Rules::DATE, 'after_or_equal:date'],
            'start_time' => [$required, Rules::TIME],
            'end_time' => [$required, Rules::TIME],
            'schedule_hour' => ['nullable'],
            'buffer_time' => ['nullable', Rules::TIME],
        ];
    }
}
