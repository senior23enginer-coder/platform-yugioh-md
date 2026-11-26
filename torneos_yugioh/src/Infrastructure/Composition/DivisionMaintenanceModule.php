<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\ApplyQualifierDivision;
use StationX\Yugioh\Application\Decks\NormalizeLegacyDeckFormat;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoQualifierDivisionStore;
use StationX\Yugioh\Infrastructure\Persistence\Decks\PdoLegacyFormatNormalizationStore;

final class DivisionMaintenanceModule
{
    public static function qualifier(PDO $pdo): ApplyQualifierDivision
    {
        return new ApplyQualifierDivision(new PdoQualifierDivisionStore($pdo));
    }

    public static function normalize(PDO $pdo): NormalizeLegacyDeckFormat
    {
        return new NormalizeLegacyDeckFormat(new PdoLegacyFormatNormalizationStore($pdo));
    }
}
