<?php

namespace App\Http\Requests\Patients;

use App\Http\Requests\Rules;

/** A walk-in patient plus the visit, logged as the signed-in staff member's duty. */
class WalkInRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'country' => ['nullable', 'string', 'max:60'],
            'role' => ['required', 'string', 'max:60'],
            'date' => ['required', Rules::DATE],
            'end_date' => ['required', Rules::DATE, 'after_or_equal:date'],
            'start_time' => ['required', Rules::TIME],
            'end_time' => ['required', Rules::TIME],
            'schedule_hour' => ['nullable'],
            'buffer_time' => ['nullable', Rules::TIME],
        ];
    }

    /** @return array{0: array, 1: array} patient fields, duty fields */
    public function parts(): array
    {
        $data = $this->validated();
        $dutyFields = ['role', 'date', 'end_date', 'start_time', 'end_time', 'schedule_hour', 'buffer_time'];

        return [
            array_diff_key($data, array_flip($dutyFields)),
            array_intersect_key($data, array_flip($dutyFields)),
        ];
    }
}
