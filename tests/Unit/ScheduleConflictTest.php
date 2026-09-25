<?php

namespace Tests\Unit;

use App\Services\ScheduleConflictService;
use PHPUnit\Framework\TestCase;

class ScheduleConflictTest extends TestCase
{
    private ScheduleConflictService $conflicts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conflicts = new ScheduleConflictService;
    }

    private function duty(string $date, string $start, string $end, ?string $endDate = null, string $buffer = '00:00'): array
    {
        return ['date' => $date, 'end_date' => $endDate ?? $date, 'start_time' => $start, 'end_time' => $end, 'buffer_time' => $buffer];
    }

    public function test_overlapping_times_on_the_same_day_conflict(): void
    {
        $this->assertTrue($this->conflicts->overlaps(
            $this->duty('2025-03-01', '09:00', '12:00'),
            $this->duty('2025-03-01', '11:00', '13:00'),
        ));
    }

    public function test_back_to_back_duties_do_not_conflict(): void
    {
        $this->assertFalse($this->conflicts->overlaps(
            $this->duty('2025-03-01', '09:00', '12:00'),
            $this->duty('2025-03-01', '12:00', '14:00'),
        ));
    }

    public function test_buffer_time_after_a_duty_is_kept_free(): void
    {
        $this->assertTrue($this->conflicts->overlaps(
            $this->duty('2025-03-01', '09:00', '12:00', buffer: '00:30'),
            $this->duty('2025-03-01', '12:15', '14:00'),
        ));
    }

    public function test_overnight_duty_blocks_the_next_morning(): void
    {
        $this->assertTrue($this->conflicts->overlaps(
            $this->duty('2025-03-01', '20:00', '08:00', '2025-03-02'),
            $this->duty('2025-03-02', '07:00', '10:00'),
        ));
        $this->assertFalse($this->conflicts->overlaps(
            $this->duty('2025-03-01', '20:00', '08:00', '2025-03-02'),
            $this->duty('2025-03-02', '09:00', '10:00'),
        ));
    }

    public function test_same_day_end_before_start_is_treated_as_overnight(): void
    {
        $this->assertTrue($this->conflicts->overlaps(
            $this->duty('2025-03-01', '22:00', '06:00'),
            $this->duty('2025-03-02', '05:00', '07:00'),
        ));
    }

    public function test_different_days_do_not_conflict(): void
    {
        $this->assertFalse($this->conflicts->overlaps(
            $this->duty('2025-03-01', '09:00', '12:00'),
            $this->duty('2025-03-02', '09:00', '12:00'),
        ));
    }
}
