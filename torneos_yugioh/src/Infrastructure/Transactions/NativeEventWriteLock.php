<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Transactions;

use PDO;
use StationX\Yugioh\Application\Shared\EventWriteLock;

/** Compatibility bridge to the existing reentrant MySQL event lock; its native owner is unchanged. */
final readonly class NativeEventWriteLock implements EventWriteLock
{
    public function __construct(private PDO $pdo) {}
    public function run(callable $operation): mixed
    {
        return \ygo_with_event_write_lock($this->pdo, $operation);
    }
}
