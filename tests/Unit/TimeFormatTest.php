<?php

namespace Tests\Unit;

use App\Support\TimeFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeFormatTest extends TestCase
{
    #[DataProvider('twelveHourCases')]
    public function test_formats_24_hour_times_for_messages(string $input, string $expected): void
    {
        $this->assertSame($expected, TimeFormat::to12Hour($input));
    }

    public static function twelveHourCases(): array
    {
        return [
            'midnight' => ['00:30', '12:30 am'],
            'morning' => ['09:05', '9:05 am'],
            'noon' => ['12:00', '12:00 pm'],
            'afternoon' => ['14:45', '2:45 pm'],
            'late' => ['23:59', '11:59 pm'],
            'with seconds' => ['18:20:11', '6:20 pm'],
            'garbage kept' => ['soon', 'soon'],
        ];
    }

    public function test_minutes_of_a_duration(): void
    {
        $this->assertSame(90, TimeFormat::minutes('01:30'));
        $this->assertSame(0, TimeFormat::minutes(null));
    }
}
