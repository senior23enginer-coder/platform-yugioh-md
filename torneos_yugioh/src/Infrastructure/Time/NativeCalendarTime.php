<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Time;

use DateTimeImmutable;
use StationX\Yugioh\Application\Calendar\CalendarTime;

/** Preserves PHP's configured timezone and native relative-date parsing. */
final class NativeCalendarTime implements CalendarTime
{
    public function parse(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }
    public function evening(): DateTimeImmutable
    {
        return new DateTimeImmutable('19:00');
    }
}
