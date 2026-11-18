<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

use DateTimeImmutable;

/** Native timezone/relative parsing is kept outside deterministic planning. */
interface CalendarTime
{
    public function parse(string $value): DateTimeImmutable;
    public function evening(): DateTimeImmutable;
}
