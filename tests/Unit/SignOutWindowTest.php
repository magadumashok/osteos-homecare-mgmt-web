<?php

namespace Tests\Unit;

use App\Services\DutyScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

/** Sign-out: open 30 minutes either side of the end; earlier needs a reason; later is refused. */
class SignOutWindowTest extends TestCase
{
    private const ZONE = 'Asia/Kolkata';

    private function check(string $now, array $data = []): ?array
    {
        $method = new ReflectionMethod(DutyScheduleService::class, 'signOutWindow');
        $service = $this->app->make(DutyScheduleService::class);
        $end = CarbonImmutable::parse('2026-11-16 17:00', self::ZONE);

        return $method->invoke($service, $end, CarbonImmutable::parse($now, self::ZONE), $data, 'session');
    }

    private function assertRefused(string $now, array $data, string $message): void
    {
        try {
            $this->check($now, $data);
            $this->fail('Sign-out was allowed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, $e->errors()['duty'][0]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'homecare.attendance.signout_early_minutes' => 30,
            'homecare.attendance.signout_late_minutes' => 30,
        ]);
    }

    public function test_signing_out_within_thirty_minutes_of_the_end_needs_no_reason(): void
    {
        $this->assertNull($this->check('2026-11-16 16:30'));
        $this->assertNull($this->check('2026-11-16 17:30'));
    }

    public function test_signing_out_earlier_without_a_reason_is_refused(): void
    {
        $this->assertRefused('2026-11-16 16:15', [], 'give a reason');
        $this->assertRefused('2026-11-16 16:15', ['early_reason' => '   '], 'give a reason');
    }

    public function test_signing_out_earlier_with_a_reason_is_recorded(): void
    {
        $early = $this->check('2026-11-16 16:15', ['early_reason' => ' Family emergency ']);

        $this->assertSame('Family emergency', $early['reason']);
        $this->assertSame('16:15', $early['at']->format('H:i'));
    }

    public function test_signing_out_after_the_window_closes_is_refused(): void
    {
        $this->assertRefused('2026-11-16 17:31', ['early_reason' => 'Late'], 'Sign-out closed at 5:30 pm');
    }
}
