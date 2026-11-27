<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Competitions\ReadAdmissionSource;
use StationX\Yugioh\Application\Competitions\ResolveAdmissionDeckFormat;
use StationX\Yugioh\Application\Competitions\ResolveEventAdmission;
use StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoAdmissionSourceReader;

final class AdmissionReadModule
{
    public static function source(PDO $pdo): ReadAdmissionSource
    {
        $reader = new PdoAdmissionSourceReader($pdo);
        return new ReadAdmissionSource($reader, new ResolveAdmissionDeckFormat($reader));
    }

    public static function resolve(PDO $pdo): ResolveEventAdmission
    {
        $reader = new PdoAdmissionSourceReader($pdo);
        return new ResolveEventAdmission(
            $reader,
            new ReadAdmissionSource($reader, new ResolveAdmissionDeckFormat($reader)),
        );
    }
}
