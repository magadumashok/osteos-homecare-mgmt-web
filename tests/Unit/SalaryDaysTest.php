<?php

namespace Tests\Unit;

use App\Services\PatientService;
use App\Services\SalaryService;
use Tests\TestCase;

class SalaryDaysTest extends TestCase
{
    private SalaryService $salary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salary = new SalaryService($this->createMock(PatientService::class));
    }

    private function duty(string $date, ?float $rate, string $signIn = '09:00'): array
    {
        return ['date' => $date, 'rate' => $rate, 'patient_id' => 'p1', 'signin_time' => $signIn, 'signout_time' => '17:00'];
    }

    public function test_several_duties_on_one_day_are_paid_once(): void
    {
        $days = $this->salary->days(collect([
            $this->duty('2026-10-01', 800, '08:00'),
            $this->duty('2026-10-01', 800, '14:00'),
            $this->duty('2026-10-02', 800),
        ]), 800);

        $this->assertCount(2, $days);
        $this->assertSame(1600.0, (float) $days->sum('rate'));
        $this->assertCount(2, $days->firstWhere('date', '2026-10-01')['duties']);
    }

    public function test_the_rate_kept_at_sign_out_wins_over_the_current_rate(): void
    {
        $days = $this->salary->days(collect([$this->duty('2026-10-01', 700)]), 900);

        $this->assertSame(700.0, $days->first()['rate']);
    }

    public function test_older_duties_without_a_kept_rate_use_the_current_rate(): void
    {
        $days = $this->salary->days(collect([$this->duty('2026-10-01', null)]), 900);

        $this->assertSame(900.0, $days->first()['rate']);
    }

    public function test_no_salary_set_pays_nothing(): void
    {
        $days = $this->salary->days(collect([$this->duty('2026-10-01', null)]), null);

        $this->assertSame(0.0, $days->first()['rate']);
    }

    public function test_days_are_newest_first(): void
    {
        $days = $this->salary->days(collect([$this->duty('2026-10-01', 1), $this->duty('2026-10-03', 1), $this->duty('2026-10-02', 1)]), 1);

        $this->assertSame(['2026-10-03', '2026-10-02', '2026-10-01'], $days->pluck('date')->all());
    }
}
