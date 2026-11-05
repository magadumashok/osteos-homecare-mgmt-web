<?php

namespace Tests\Unit;

use App\Models\StaffDutySchedule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DutyDayWindowTest extends TestCase
{
    private const ZONE = 'Asia/Kolkata';

    private function duty(string $start): StaffDutySchedule
    {
        return new StaffDutySchedule(['day_start_time' => $start]);
    }

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment, self::ZONE);
    }

    public function test_sign_in_is_allowed_ten_minutes_either_side_of_the_start(): void
    {
        $duty = $this->duty('08:00');

        $this->assertSame('2026-11-05', $duty->signInDayAt($this->at('2026-11-05 07:50'), 10, 10));
        $this->assertSame('2026-11-05', $duty->signInDayAt($this->at('2026-11-05 08:10'), 10, 10));
    }

    public function test_sign_in_is_refused_outside_the_window(): void
    {
        $duty = $this->duty('08:00');

        $this->assertNull($duty->signInDayAt($this->at('2026-11-05 07:49'), 10, 10));
        $this->assertNull($duty->signInDayAt($this->at('2026-11-05 08:11'), 10, 10));
    }

    public function test_early_and_late_limits_can_differ(): void
    {
        $duty = $this->duty('08:00');

        $this->assertNull($duty->signInDayAt($this->at('2026-11-05 07:54'), 5, 10));
        $this->assertSame('2026-11-05', $duty->signInDayAt($this->at('2026-11-05 08:10'), 5, 10));
    }

    public function test_a_start_just_after_midnight_signs_in_for_the_next_day(): void
    {
        $this->assertSame('2026-11-06', $this->duty('00:02')->signInDayAt($this->at('2026-11-05 23:58'), 10, 10));
    }

    public function test_a_start_just_before_midnight_signs_in_for_the_previous_day(): void
    {
        $this->assertSame('2026-11-05', $this->duty('23:58')->signInDayAt($this->at('2026-11-06 00:05'), 10, 10));
    }

    public function test_a_day_ends_at_its_start_time_the_next_day(): void
    {
        $end = $this->duty('08:00')->dayEndsAt('2026-11-30', self::ZONE);

        $this->assertSame('2026-12-01 08:00', $end->format('Y-m-d H:i'));
    }
}
