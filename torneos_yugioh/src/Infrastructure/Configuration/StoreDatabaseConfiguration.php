<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Configuration;

use RuntimeException;

final class StoreDatabaseConfiguration
{
    public static function load(string $root): array
    {
        $local = $root . '/config/database.local.php';
        $legacy = getenv('STATION_X_DOLIBARR_CONFIG') ?: $root . '/htdocs/conf/conf.php';
        if (is_file($local)) {
            $config = require $local;
            if (!is_array($config)) {
                throw new RuntimeException('database.local.php debe devolver un array de configuración.');
            }
        } elseif (is_file($legacy)) {
            require $legacy;
            $config = [
                'host' => $dolibarr_main_db_host ?? 'localhost',
                'port' => $dolibarr_main_db_port ?? '',
                'database' => $dolibarr_main_db_name ?? '',
                'username' => $dolibarr_main_db_user ?? '',
                'password' => $dolibarr_main_db_pass ?? '',
                'prefix' => $dolibarr_main_db_prefix ?? 'llx_',
                'entity' => $dolibarr_main_entity ?? 1,
            ];
        } else {
            $config = [
                'host' => getenv('YGO_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('YGO_DB_PORT') ?: '3306',
                'database' => getenv('YGO_DB_NAME') ?: '',
                'username' => getenv('YGO_DB_USER') ?: '',
                'password' => getenv('YGO_DB_PASSWORD') ?: '',
                'prefix' => getenv('YGO_DB_PREFIX') ?: 'llx_',
                'entity' => (int) (getenv('YGO_DB_ENTITY') ?: 1),
            ];
        }
        if (empty($config['database']) || empty($config['username'])) {
            throw new RuntimeException('Configura config/database.local.php o las variables YGO_DB_* antes de iniciar la plataforma.');
        }
        return $config;
    }
}
