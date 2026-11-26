<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Competitions\CheckCompetitionGate;
use StationX\Yugioh\Application\Competitions\EvaluateCompetitionDependency;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionDependencyReader;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class CompetitionGateModule
{
    public static function reader(PDO $pdo): PdoCompetitionDependencyReader
    {
        return new PdoCompetitionDependencyReader($pdo);
    }

    public static function dependency(PDO $pdo): EvaluateCompetitionDependency
    {
        return new EvaluateCompetitionDependency(self::reader($pdo));
    }

    public static function gate(PDO $pdo): CheckCompetitionGate
    {
        $reader = self::reader($pdo);
        return new CheckCompetitionGate(
            $reader,
            $reader,
            new EvaluateCompetitionDependency($reader),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
