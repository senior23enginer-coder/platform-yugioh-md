<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Shared;

/**
 * Runs a use case in its transaction boundary.
 * A caller-owned transaction remains the caller's responsibility; nested commits are forbidden.
 */
interface TransactionBoundary
{
    public function run(callable $operation): mixed;
}
