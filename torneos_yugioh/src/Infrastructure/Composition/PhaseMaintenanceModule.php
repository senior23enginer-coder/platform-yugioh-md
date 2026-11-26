<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use PDO;
use StationX\Yugioh\Application\Calendar\AutoScheduleQualifier;
use StationX\Yugioh\Application\Competitions\AdvanceLegacyPhase;
use StationX\Yugioh\Application\Competitions\CreateKnockoutRound;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoQualifierAutoScheduleStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativeKnockoutRoundServices;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoKnockoutRoundStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoLegacyPhaseStore;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class PhaseMaintenanceModule
{
    public static function knockout(PDO $pdo): CreateKnockoutRound
    {
        return new CreateKnockoutRound(
            new PdoKnockoutRoundStore($pdo),
            new NativeKnockoutRoundServices($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }

    public static function legacy(PDO $pdo): AdvanceLegacyPhase
    {
        return new AdvanceLegacyPhase(new PdoLegacyPhaseStore($pdo));
    }

    public static function qualifier(PDO $pdo): AutoScheduleQualifier
    {
        return new AutoScheduleQualifier(
            new PdoQualifierAutoScheduleStore($pdo),
            QualifierFinalizationModule::create($pdo),
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }
}
