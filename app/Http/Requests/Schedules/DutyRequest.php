<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\Rules;
use App\Models\StaffDutySchedule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Schedule (POST) or reschedule (PUT) a staff duty. `driver` is the driver's name.
 * `live_in` (24 hours) duties take only dates and `care_tasks`; their times are
 * set by the server. Older apps send no `live_in`: a caretaker without times is live-in.
 */
class DutyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $liveIn = $this->has('live_in')
            ? $this->boolean('live_in')
            : StaffDutySchedule::isLiveInRole($this->input('role')) && ! $this->filled('start_time');
        $time = $liveIn ? ['nullable', Rules::TIME] : [$required, Rules::TIME];

        return [
            'staff_id' => [$required, 'string'],
            'role' => [$required, 'string', 'max:60'],
            'patient_id' => [$this->isMethod('post') ? 'required' : 'prohibited', 'string'],
            'driver' => ['nullable', 'string', 'max:120'],
            'date' => [$required, Rules::DATE],
            'end_date' => [$required, Rules::DATE, 'after_or_equal:date'],
            'start_time' => $time,
            'end_time' => $time,
            'schedule_hour' => ['nullable'],
            'buffer_time' => ['nullable', Rules::TIME],
            'live_in' => ['nullable', 'boolean'],
            'care_tasks' => ['nullable', 'array', 'max:40'],
            'care_tasks.*' => ['string', 'max:120'],
            'service' => ['nullable', 'string', 'max:120'],
            'fees' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
