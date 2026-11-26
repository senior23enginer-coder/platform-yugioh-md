<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\ManageCompetitionDependencies;
use StationX\Yugioh\Application\Competitions\SaveParticipantCheckin;
use StationX\Yugioh\Application\Commands\SelectLogoHistory;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoDependencyWriteStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoParticipantCheckinStore;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoLogoHistoryStore;

final class CompetitionAdministrativeModule
{
    public static function dependencies(PDO $pdo): ManageCompetitionDependencies
    {
        return new ManageCompetitionDependencies(new PdoDependencyWriteStore($pdo));
    }

    public static function checkin(PDO $pdo): SaveParticipantCheckin
    {
        return new SaveParticipantCheckin(new PdoParticipantCheckinStore($pdo));
    }

    public static function logo(PDO $pdo): SelectLogoHistory
    {
        return new SelectLogoHistory(new PdoLogoHistoryStore($pdo));
    }
}
