<?php
declare(strict_types=1);

/** Compatibility bridge: only the three Station X functions used by Yu-Gi-Oh. */
function sx_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['sx_csrf'])) $_SESSION['sx_csrf'] = bin2hex(random_bytes(32));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function connect_store_common(string &$prefix, int &$entity): PDO
{
    require_once __DIR__ . '/torneos_yugioh/src/autoload.php';
    $config = \StationX\Yugioh\Infrastructure\Configuration\StoreDatabaseConfiguration::load(__DIR__);
    return \StationX\Yugioh\Infrastructure\Persistence\StoreConnectionFactory::connect($config, $prefix, $entity);
}
