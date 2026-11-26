<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Teams\FindOrCreateTeam;
use StationX\Yugioh\Application\Teams\SyncDeckTeams;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoDeckTeamStore;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoTeamStore;

final class TeamModule
{
    public static function findOrCreate(PDO $pdo): FindOrCreateTeam
    {
        return new FindOrCreateTeam(new PdoTeamStore($pdo));
    }

    public static function sync(PDO $pdo): SyncDeckTeams
    {
        return new SyncDeckTeams(new PdoDeckTeamStore($pdo), self::findOrCreate($pdo));
    }
}
