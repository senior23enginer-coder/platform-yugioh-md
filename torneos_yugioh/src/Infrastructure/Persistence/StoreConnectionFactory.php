<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence;

use PDO;

final class StoreConnectionFactory
{
    public static function connect(array $config, string &$prefix, int &$entity): PDO
    {
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($config['prefix'] ?? 'llx_'));
        $entity = (int) ($config['entity'] ?? 1);
        $dsn = 'mysql:host=' . $config['host'] . (!empty($config['port']) ? ';port=' . $config['port'] : '')
            . ';dbname=' . $config['database'] . ';charset=utf8mb4';
        return new PDO($dsn, (string) $config['username'], (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
