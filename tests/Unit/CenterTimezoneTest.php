<?php

namespace Tests\Unit;

use App\Services\NoteService;
use App\Services\TenantService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CenterTimezoneTest extends TestCase
{
    public function test_a_country_code_gives_its_timezone(): void
    {
        $this->assertSame('Asia/Kolkata', TenantService::zoneOfCountry('IN'));
        $this->assertSame('Asia/Dubai', TenantService::zoneOfCountry('ae'));
    }

    public function test_country_names_older_centers_stored_give_their_timezone(): void
    {
        $this->assertSame('Asia/Kolkata', TenantService::zoneOfCountry('India'));
        $this->assertSame('Asia/Dubai', TenantService::zoneOfCountry('UAE'));
    }

    public function test_an_unknown_country_gives_no_timezone(): void
    {
        $this->assertNull(TenantService::zoneOfCountry(null));
        $this->assertNull(TenantService::zoneOfCountry(''));
        $this->assertNull(TenantService::zoneOfCountry('Atlantis'));
        $this->assertNull(TenantService::zoneOfCountry('ZZ'));
    }

    public function test_the_reported_time_reads_correctly_in_the_center_timezone(): void
    {
        config(['homecare.attendance.timezone' => 'Asia/Kolkata']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 09:08:00', 'UTC'));

        $this->assertSame('14:38', app(TenantService::class)->now()->format('H:i'));

        CarbonImmutable::setTestNow();
    }

    public function test_after_midnight_at_the_center_it_is_already_tomorrow(): void
    {
        config(['homecare.attendance.timezone' => 'Asia/Kolkata']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 19:00:00', 'UTC'));

        $this->assertSame('2026-11-08', app(TenantService::class)->today());

        CarbonImmutable::setTestNow();
    }

    public function test_a_note_without_a_time_gets_the_time_at_the_center(): void
    {
        config(['homecare.attendance.timezone' => 'Asia/Kolkata']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 19:00:30', 'UTC'));

        $when = new \ReflectionMethod(NoteService::class, 'when');

        $this->assertSame(
            ['date' => '2026-11-08', 'time' => '00:30:30'],
            $when->invoke(app(NoteService::class), []),
        );

        CarbonImmutable::setTestNow();
    }
}
