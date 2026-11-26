<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\SaveCompetitionDefinition;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoCompetitionDefinitionStore;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\NativeCompetitionDefinitionServices;

final class CompetitionDefinitionModule
{
    public static function create(PDO $pdo): SaveCompetitionDefinition
    {
        return new SaveCompetitionDefinition(new PdoCompetitionDefinitionStore($pdo), new NativeCompetitionDefinitionServices($pdo));
    }
}
