<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Shared;

use DateTimeImmutable;

/** Supplies application time without binding use cases to the system clock. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
