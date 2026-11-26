<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Seasons\GuardSeasonAction;
use StationX\Yugioh\Application\Seasons\HandleSeasonCommand;
use StationX\Yugioh\Application\Seasons\ResolveSeasonContext;
use StationX\Yugioh\Infrastructure\Persistence\Seasons\PdoSeasonContextReader;
use StationX\Yugioh\Infrastructure\Persistence\Seasons\PdoSeasonScheduleReader;

final class SeasonContextModule
{
    public static function context(PDO $pdo): ResolveSeasonContext
    {
        return new ResolveSeasonContext(new PdoSeasonContextReader($pdo));
    }

    public static function guard(PDO $pdo): GuardSeasonAction
    {
        return new GuardSeasonAction(
            SeasonScheduleModule::create($pdo),
            new PdoSeasonContextReader($pdo),
        );
    }

    public static function commands(PDO $pdo): HandleSeasonCommand
    {
        return new HandleSeasonCommand(
            SeasonLifecycleModule::create($pdo),
            new PdoSeasonScheduleReader($pdo),
            self::context($pdo),
        );
    }
}
