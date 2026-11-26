<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\GenerateLegacyEventFixtures;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoLegacyEventFixtureReader;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoLegacyEventFixtureWriter;

final class LegacyEventFixtureModule
{
    public static function create(PDO $pdo): GenerateLegacyEventFixtures
    {
        return new GenerateLegacyEventFixtures(
            new PdoLegacyEventFixtureReader($pdo),
            new PdoLegacyEventFixtureWriter($pdo),
        );
    }
}
