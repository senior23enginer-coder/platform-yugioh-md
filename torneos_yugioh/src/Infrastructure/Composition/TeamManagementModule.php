<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Teams\SavePlayer;
use StationX\Yugioh\Application\Teams\SaveTeam;
use StationX\Yugioh\Application\Teams\UpdateTeamDeckLinks;
use StationX\Yugioh\Infrastructure\Persistence\Teams\NativeTeamLogoService;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoPlayerStore;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoTeamDeckLinkStore;
use StationX\Yugioh\Infrastructure\Persistence\Teams\PdoTeamProfileStore;
use StationX\Yugioh\Infrastructure\Transactions\PdoTransactionBoundary;

final class TeamManagementModule
{
    public static function links(PDO $pdo): UpdateTeamDeckLinks
    {
        return new UpdateTeamDeckLinks(new PdoTeamDeckLinkStore($pdo));
    }

    public static function player(PDO $pdo): SavePlayer
    {
        return new SavePlayer(new PdoPlayerStore($pdo), new PdoTransactionBoundary($pdo, false));
    }

    public static function team(PDO $pdo): SaveTeam
    {
        return new SaveTeam(new PdoTeamProfileStore($pdo), new NativeTeamLogoService($pdo));
    }
}
