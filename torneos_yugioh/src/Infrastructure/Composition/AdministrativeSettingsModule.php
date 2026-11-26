<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Matches\ManageGameRules;
use StationX\Yugioh\Application\Settings\SaveAdministrativeSettings;
use StationX\Yugioh\Infrastructure\Persistence\Matches\PdoGameRuleWriteStore;
use StationX\Yugioh\Infrastructure\Persistence\Settings\PdoAdministrativeStore;

final class AdministrativeSettingsModule
{
    public static function rules(PDO $pdo): ManageGameRules
    {
        return new ManageGameRules(new PdoGameRuleWriteStore($pdo));
    }

    public static function settings(PDO $pdo): SaveAdministrativeSettings
    {
        return new SaveAdministrativeSettings(new PdoAdministrativeStore($pdo));
    }
}
