<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use StationX\Yugioh\Application\Shared\Clock;

/** Uses the platform's established timezone, regardless of the server's default timezone. */
final readonly class SystemClock implements Clock
{
    public function __construct(private DateTimeZone $zone = new DateTimeZone('America/Bogota')) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->zone);
    }
}
