<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\FinalizeQualifier;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoQualifierFinalizationReader;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoQualifierFinalizationWriter;

final class QualifierFinalizationModule
{
    public static function create(PDO $pdo): FinalizeQualifier
    {
        return new FinalizeQualifier(
            new PdoQualifierFinalizationReader($pdo),
            new PdoQualifierFinalizationWriter($pdo),
        );
    }
}
