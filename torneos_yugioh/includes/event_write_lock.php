<?php
declare(strict_types=1);

/** Serialize calendar/roster writes across PHP workers, including different events.
 * Reentrant on the same PDO connection. The caller retains transaction ownership.
 */
function ygo_with_event_write_lock(PDO $pdo, callable $operation): mixed
{
    static $depths;
    $depths ??= new WeakMap();
    if (($depths[$pdo] ?? 0) > 0) return $operation();
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $name = 'station_x_events_'.substr(hash('sha256', $database), 0, 32);
    $q = $pdo->prepare('SELECT GET_LOCK(?, 15)');
    $q->execute([$name]);
    if ((string)$q->fetchColumn() !== '1') {
        throw new InvalidArgumentException('Otro proceso esta actualizando los eventos. Espera unos segundos e intenta nuevamente.');
    }
    $depths[$pdo] = 1;
    try { return $operation(); }
    finally {
        unset($depths[$pdo]);
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$name]);
    }
}
