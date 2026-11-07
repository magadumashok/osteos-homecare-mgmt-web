<?php

namespace Tests\Unit;

use App\Jobs\SendWhatsAppTemplate;
use App\Models\Patient;
use App\Services\PatientService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'homecare.twilio.sid' => 'test',
            'homecare.twilio.token' => 'test',
            'homecare.twilio.whatsapp_from' => '+10000000000',
        ]);
    }

    public function test_blank_values_are_never_sent_empty(): void
    {
        $this->assertSame(WhatsAppService::BLANK, WhatsAppService::variable(null));
        $this->assertSame(WhatsAppService::BLANK, WhatsAppService::variable(''));
        $this->assertSame(WhatsAppService::BLANK, WhatsAppService::variable("  \n\t "));
    }

    public function test_multi_line_addresses_become_one_line(): void
    {
        $this->assertSame('12 MG Road, Bengaluru', WhatsAppService::variable("12 MG Road,\r\n\tBengaluru   "));
    }

    public function test_real_values_are_kept(): void
    {
        $link = 'https://www.google.com/maps/dir/?api=1&destination=12+MG+Road';

        $this->assertSame($link, WhatsAppService::variable($link));
        $this->assertSame('0', WhatsAppService::variable(0));
    }

    /** The no-driver duty template, for a patient with no address or link (the reported failure). */
    public function test_a_duty_for_a_patient_without_an_address_fills_every_placeholder(): void
    {
        Bus::fake();

        app(WhatsAppService::class)->send('+919900000000', 'staff_duty_no_driver', ['Asha', 'Ravi', '2026-11-07', '9:00 am', null, null, 'not provided']);

        Bus::assertDispatched(SendWhatsAppTemplate::class, function (SendWhatsAppTemplate $job) {
            $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], array_map('strval', array_keys($job->variables)));
            $this->assertNotContains('', $job->variables);

            return $job->variables[5] === WhatsAppService::BLANK && $job->variables[6] === WhatsAppService::BLANK;
        });
    }

    /** With QUEUE_CONNECTION=sync a Twilio error happens inside the request. */
    public function test_a_failed_send_does_not_fail_the_caller(): void
    {
        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('[HTTP 400] Unable to create record'));
        });

        app(WhatsAppService::class)->send('+919900000000', 'staff_duty', ['Asha']);

        $this->addToAssertionCount(1);
    }

    public function test_directions_are_built_from_the_address_when_no_link_is_saved(): void
    {
        $patient = new Patient(['address' => '12 MG Road', 'city' => 'Bengaluru']);

        $this->assertStringContainsString('12+MG+Road+Bengaluru', app(PatientService::class)->directionsLink($patient));
    }

    public function test_a_saved_directions_link_is_used_as_it_is(): void
    {
        $patient = new Patient(['address' => '12 MG Road', 'address_link' => 'https://maps.example/saved']);

        $this->assertSame('https://maps.example/saved', app(PatientService::class)->directionsLink($patient));
    }

    public function test_a_patient_with_no_address_has_no_directions(): void
    {
        $this->assertNull(app(PatientService::class)->directionsLink(new Patient));
    }
}
