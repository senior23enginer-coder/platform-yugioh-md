<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Leagues\LeagueRoster;
use StationX\Yugioh\Application\Leagues\PlanLeagueSchedule;
use StationX\Yugioh\Application\Leagues\ValidateFormatRoster;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoFormatRosterReader;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoLeagueRosterReader;
use StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoLeagueScheduleReader;

final class LeagueRosterModule
{
    public static function format(PDO $pdo): ValidateFormatRoster
    {
        return new ValidateFormatRoster(new PdoFormatRosterReader($pdo));
    }

    public static function roster(PDO $pdo): LeagueRoster
    {
        return new LeagueRoster(new PdoLeagueRosterReader($pdo));
    }

    public static function schedule(PDO $pdo): PlanLeagueSchedule
    {
        return new PlanLeagueSchedule(new PdoLeagueScheduleReader($pdo));
    }
}
