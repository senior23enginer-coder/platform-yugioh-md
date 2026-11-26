<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Calendar\ScheduleQualifierFixtures;
use StationX\Yugioh\Application\Calendar\ScheduleCupFixtures;
use StationX\Yugioh\Application\Calendar\ScheduleModelFixtures;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoFixtureGenerationReader;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\PdoFixtureGenerationWriter;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\NativeFixtureGenerationRules;
use StationX\Yugioh\Infrastructure\Persistence\Calendar\NativeFixtureGenerationActions;

final class FixtureGenerationModule
{
    public static function qualifier(PDO $pdo): ScheduleQualifierFixtures
    {
        return new ScheduleQualifierFixtures(
            new PdoFixtureGenerationReader($pdo),
            new NativeFixtureGenerationRules($pdo),
            new NativeFixtureGenerationActions($pdo),
        );
    }

    public static function cup(PDO $pdo): ScheduleCupFixtures
    {
        return new ScheduleCupFixtures(
            new PdoFixtureGenerationReader($pdo),
            new PdoFixtureGenerationWriter($pdo),
            new NativeFixtureGenerationRules($pdo),
            new NativeFixtureGenerationActions($pdo),
        );
    }

    public static function model(PDO $pdo): ScheduleModelFixtures
    {
        return new ScheduleModelFixtures(
            new PdoFixtureGenerationReader($pdo),
            new PdoFixtureGenerationWriter($pdo),
            new NativeFixtureGenerationRules($pdo),
            new NativeFixtureGenerationActions($pdo),
        );
    }
}
