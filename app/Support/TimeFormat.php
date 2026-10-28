<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Times are stored the way the legacy clients send them: "HH:MM" strings
 * next to "Y-m-d" date strings.
 */
final class TimeFormat
{
    /** "14:05" → "2:05 pm", "00:30" → "12:30 am". Unparseable input is returned unchanged. */
    public static function to12Hour(?string $time): string
    {
        $time = trim((string) $time);

        if (! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return $time;
        }

        $hour = (int) $m[1];
        $suffix = $hour >= 12 ? 'pm' : 'am';
        $hour = $hour % 12 ?: 12;

        return "{$hour}:{$m[2]} {$suffix}";
    }

    /** Combine a "Y-m-d" date and an "HH:MM" time (seconds zero), optionally in a timezone. */
    public static function at(string $date, ?string $time, ?string $timezone = null): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.self::hhmm($time), $timezone);
    }

    /** "01:30" → 90. */
    public static function minutes(?string $time): int
    {
        [$hours, $minutes] = explode(':', self::hhmm($time));

        return (int) $hours * 60 + (int) $minutes;
    }

    private static function hhmm(?string $time): string
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', trim((string) $time), $m)
            ? sprintf('%02d:%s', $m[1], $m[2])
            : '00:00';
    }
}
