<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Settings\ReadSettings;
use StationX\Yugioh\Application\Settings\SettingsWriter;
use StationX\Yugioh\Infrastructure\Persistence\Settings\PdoSettingsReader;
use StationX\Yugioh\Infrastructure\Persistence\Settings\PdoSettingsWriter;

final class SettingsModule
{
    public static function read(PDO $pdo): ReadSettings
    {
        return new ReadSettings(new PdoSettingsReader($pdo));
    }

    public static function writer(PDO $pdo): SettingsWriter
    {
        return new PdoSettingsWriter($pdo);
    }
}
