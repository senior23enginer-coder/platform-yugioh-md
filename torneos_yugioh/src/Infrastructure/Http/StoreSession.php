<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http;

final class StoreSession
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['sx_csrf'])) {
            $_SESSION['sx_csrf'] = bin2hex(random_bytes(32));
        }
    }
}
