<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Calendar\PublishCompetitionCalendar;
use StationX\Yugioh\Application\Competitions\PublishStandingSnapshots;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoCompetitionCalendarReader;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoCompetitionCalendarWriter;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoStandingSnapshotReader;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoStandingSnapshotWriter;

final class CompetitionProjectionModule
{
    public static function standings(PDO $pdo): PublishStandingSnapshots
    {
        return new PublishStandingSnapshots(
            new PdoStandingSnapshotReader($pdo),
            new PdoStandingSnapshotWriter($pdo),
        );
    }

    public static function calendar(PDO $pdo): PublishCompetitionCalendar
    {
        return new PublishCompetitionCalendar(
            new PdoCompetitionCalendarReader($pdo),
            new PdoCompetitionCalendarWriter($pdo),
        );
    }
}
