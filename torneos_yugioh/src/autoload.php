<?php
declare(strict_types=1);

if (!defined('YGO_APP_ROOT')) define('YGO_APP_ROOT', dirname(__DIR__));
if (!defined('YGO_ENTRY_FILE')) define('YGO_ENTRY_FILE', YGO_APP_ROOT . '/index.php');

// Composition support only. Loading the core must never initialize HTTP or a database.
spl_autoload_register(static function (string $class): void {
    $prefix = 'StationX\\Yugioh\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require_once $path;
});
