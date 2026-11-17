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
    $local = __DIR__ . '/config/database.local.php';
    $legacy = getenv('STATION_X_DOLIBARR_CONFIG') ?: __DIR__ . '/htdocs/conf/conf.php';
    if (is_file($local)) {
        $config = require $local;
        if (!is_array($config)) throw new RuntimeException('database.local.php debe devolver un array de configuración.');
    } elseif (is_file($legacy)) {
        // Existing Station X hosting can keep its private Dolibarr configuration outside Git.
        require $legacy;
        $config = ['host'=>$dolibarr_main_db_host ?? 'localhost', 'port'=>$dolibarr_main_db_port ?? '',
            'database'=>$dolibarr_main_db_name ?? '', 'username'=>$dolibarr_main_db_user ?? '',
            'password'=>$dolibarr_main_db_pass ?? '', 'prefix'=>$dolibarr_main_db_prefix ?? 'llx_',
            'entity'=>$dolibarr_main_entity ?? 1];
    } else {
        $config = ['host'=>getenv('YGO_DB_HOST') ?: '127.0.0.1', 'port'=>getenv('YGO_DB_PORT') ?: '3306',
            'database'=>getenv('YGO_DB_NAME') ?: '', 'username'=>getenv('YGO_DB_USER') ?: '',
            'password'=>getenv('YGO_DB_PASSWORD') ?: '', 'prefix'=>getenv('YGO_DB_PREFIX') ?: 'llx_',
            'entity'=>(int)(getenv('YGO_DB_ENTITY') ?: 1)];
    }
    if (empty($config['database']) || empty($config['username'])) {
        throw new RuntimeException('Configura config/database.local.php o las variables YGO_DB_* antes de iniciar la plataforma.');
    }
    $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($config['prefix'] ?? 'llx_'));
    $entity = (int)($config['entity'] ?? 1);
    $dsn = 'mysql:host=' . $config['host'] . (!empty($config['port']) ? ';port=' . $config['port'] : '')
        . ';dbname=' . $config['database'] . ';charset=utf8mb4';
    return new PDO($dsn, (string)$config['username'], (string)($config['password'] ?? ''), [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
}
