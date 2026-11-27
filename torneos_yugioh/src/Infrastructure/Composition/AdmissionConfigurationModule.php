<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use DateTimeZone;
use StationX\Yugioh\Application\Competitions\AdmissionConfiguration;
use StationX\Yugioh\Application\Competitions\ListAdmissionSources;
use StationX\Yugioh\Application\Competitions\GuardAdmissionResult;
use StationX\Yugioh\Application\Competitions\ScheduleAdmissionStage;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionConfigurationStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionSourceReader;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionOperations;
use StationX\Yugioh\Infrastructure\Time\SystemClock;

final class AdmissionConfigurationModule
{
    public static function configuration(PDO $pdo): AdmissionConfiguration
    {
        return new AdmissionConfiguration(new PdoAdmissionConfigurationStore($pdo), new PdoAdmissionSourceReader($pdo), AdmissionReadModule::resolve($pdo));
    }

    public static function directory(PDO $pdo): ListAdmissionSources
    {
        return new ListAdmissionSources(new PdoAdmissionOperations($pdo), AdmissionReadModule::source($pdo));
    }

    public static function resultGuard(PDO $pdo): GuardAdmissionResult
    {
        return new GuardAdmissionResult(new PdoAdmissionOperations($pdo));
    }

    public static function schedule(PDO $pdo): ScheduleAdmissionStage
    {
        return new ScheduleAdmissionStage(new PdoAdmissionConfigurationStore($pdo), new PdoAdmissionOperations($pdo), new SystemClock(new DateTimeZone(date_default_timezone_get())));
    }
}
