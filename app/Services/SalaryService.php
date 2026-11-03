<?php

namespace App\Services;

use App\Models\DutyDay;
use App\Models\StaffDutySchedule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Staff pay: `daily_salary` for every day worked. A day is worked when
 * the staff member signed in and out of a visit that started that day, or
 * of that day of a 24-hour duty, whatever the hours. Several duties on one
 * day still count as one day.
 *
 * Each completed duty keeps the rate at sign-out, so changing a salary
 * does not change days already worked; older duties use the current rate.
 */
class SalaryService
{
    public function __construct(private readonly PatientService $patients) {}

    /**
     * @return array{staff_id: string, staff_name: string, daily_salary: ?float, currency: string, from: string, to: string,
     *               days_worked: int, total: float, days: list<array{date: string, rate: float, duties: list<array>}>}
     */
    public function earnings(User $staff, string $from, string $to): array
    {
        $current = $this->rate($staff->daily_salary);

        $visits = StaffDutySchedule::where('staff_id', $staff->username)
            ->where('live_in', '!=', true)
            ->where('sign_in', true)
            ->where('sign_out', true)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->get();
        $stays = DutyDay::where('staff_id', $staff->username)
            ->where('sign_in', true)
            ->where('sign_out', true)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->get();

        $patients = $this->patients->byUsername($visits->pluck('patient_id')->concat($stays->pluck('patient_id')), ['username', 'patient_name']);
        $entry = fn ($record, bool $liveIn) => [
            'date' => $record->date,
            'patient_id' => $record->patient_id,
            'patient_name' => $patients->get($record->patient_id)?->patient_name,
            'live_in' => $liveIn,
            'signin_time' => $record->signin_time,
            'signout_time' => $record->signout_time,
            'rate' => $this->rate($record->daily_salary),
        ];

        $days = $this->days($visits->map(fn ($v) => $entry($v, false))->concat($stays->map(fn ($d) => $entry($d, true))), $current);

        return [
            'staff_id' => $staff->username,
            'staff_name' => $staff->displayName(),
            'daily_salary' => $current,
            'currency' => (string) config('homecare.twilio.currency'),
            'from' => $from,
            'to' => $to,
            'days_worked' => $days->count(),
            'total' => round((float) $days->sum('rate'), 2),
            'days' => $days->all(),
        ];
    }

    /**
     * Completed duties ({date, rate, ...}) as worked days, newest first. A
     * day is paid once, at the highest rate kept on its duties, else the
     * current rate.
     *
     * @param  Collection<int, array{date: string, rate: ?float}>  $duties
     * @return Collection<int, array{date: string, rate: float, duties: list<array>}>
     */
    public function days(Collection $duties, ?float $currentRate): Collection
    {
        return $duties->groupBy('date')
            ->map(fn (Collection $onDay, string $date) => [
                'date' => $date,
                'rate' => (float) ($onDay->pluck('rate')->filter(fn ($r) => $r !== null)->max() ?? $currentRate ?? 0.0),
                'duties' => $onDay->map(fn ($d) => array_diff_key($d, ['rate' => 0, 'date' => 0]))->sortBy('signin_time')->values()->all(),
            ])
            ->sortKeysDesc()
            ->values();
    }

    private function rate(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
