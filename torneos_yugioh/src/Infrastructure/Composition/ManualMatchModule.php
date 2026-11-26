<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Matches\CreateManualMatch;
use StationX\Yugioh\Infrastructure\Persistence\Matches\NativeManualMatchRules;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoManualMatchStore;

final class ManualMatchModule
{
    public static function create(PDO $pdo): CreateManualMatch
    {
        return new CreateManualMatch(new PdoManualMatchStore($pdo), new NativeManualMatchRules($pdo));
    }
}
