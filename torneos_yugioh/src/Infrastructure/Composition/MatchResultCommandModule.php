<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Matches\PublishMatchSnapshots;
use StationX\Yugioh\Application\Matches\SaveMatchResult;
use StationX\Yugioh\Infrastructure\Persistence\Matches\NativeMatchResultEffects;
use StationX\Yugioh\Infrastructure\Persistence\Matches\NativeMatchResultRules;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoMatchResultStore;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoMatchSnapshotStore;
use StationX\Yugioh\Infrastructure\Time\SystemClock;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class MatchResultCommandModule
{
    public static function create(PDO $pdo): SaveMatchResult
    {
        return new SaveMatchResult(
            new PdoMatchResultStore($pdo),
            new NativeMatchResultRules($pdo),
            new NativeMatchResultEffects($pdo),
            new PublishMatchSnapshots(new PdoMatchSnapshotStore($pdo)),
            new PdoTransactionBoundary($pdo, false),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
