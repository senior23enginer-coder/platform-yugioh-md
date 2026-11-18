<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Shared;

/** Serializes native event/roster writes; nested operations retain the same connection/lock owner. */
interface EventWriteLock
{
    public function run(callable $operation): mixed;
}
