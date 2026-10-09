<?php

namespace Tests\Feature;

use App\Jobs\SendSms;
use App\Models\User;
use App\Services\OtpService;
use App\Services\ReferenceDataService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private OtpService $otp;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'homecare.twilio.sid' => 'AC_test',
            'homecare.twilio.token' => 'secret',
            'homecare.twilio.sms_from' => '+15550000000',
        ]);
        Bus::fake();

        $reference = $this->createMock(ReferenceDataService::class);
        $reference->method('userNumber')->willReturn('+971500000000');
        $this->otp = new OtpService($reference);

        $this->user = new User(['username' => 'n0001']);
        $this->user->_id = 'user-1';
    }

    public function test_the_texted_code_verifies_once(): void
    {
        $code = $this->sendAndCapture(OtpService::LOGIN);

        $this->assertTrue($this->otp->verify($this->user, OtpService::LOGIN, $code));
        $this->assertFalse($this->otp->verify($this->user, OtpService::LOGIN, $code));
    }

    public function test_a_code_only_works_for_its_purpose(): void
    {
        $code = $this->sendAndCapture(OtpService::LOGIN);

        $this->assertFalse($this->otp->verify($this->user, OtpService::RESET, $code));
    }

    public function test_too_many_wrong_guesses_burn_the_code(): void
    {
        $code = $this->sendAndCapture(OtpService::RESET);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($this->otp->verify($this->user, OtpService::RESET, $wrong));
        }

        $this->assertFalse($this->otp->verify($this->user, OtpService::RESET, $code));
    }

    public function test_resending_within_the_cooldown_sends_nothing(): void
    {
        $this->otp->send($this->user, OtpService::LOGIN);
        $this->otp->send($this->user, OtpService::LOGIN);

        Bus::assertDispatchedTimes(SendSms::class, 1);
    }

    public function test_the_code_is_not_kept_in_plain_text(): void
    {
        $code = $this->sendAndCapture(OtpService::LOGIN);

        $this->assertStringNotContainsString($code, serialize(Cache::get('otp:login:user-1')));
    }

    private function sendAndCapture(string $purpose): string
    {
        $this->otp->send($this->user, $purpose);

        $code = null;
        Bus::assertDispatched(SendSms::class, function (SendSms $job) use (&$code) {
            preg_match('/\b(\d{6})\b/', $job->body, $m);
            $code = $m[1] ?? null;

            return $job->to === '+971500000000';
        });

        $this->assertNotNull($code);

        return $code;
    }
}
