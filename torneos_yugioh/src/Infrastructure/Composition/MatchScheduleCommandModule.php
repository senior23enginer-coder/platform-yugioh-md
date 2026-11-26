<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Calendar\SaveMatchSchedule;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\NativeMatchScheduleServices;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoMatchScheduleStore;

final class MatchScheduleCommandModule
{
    public static function create(PDO $pdo): SaveMatchSchedule
    {
        return new SaveMatchSchedule(
            new PdoMatchScheduleStore($pdo),
            new NativeMatchScheduleServices($pdo),
        );
    }
}
