<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use StationX\Yugioh\Application\Commands\OfflineFormExecutor;

final readonly class NativeOfflineFormExecutor implements OfflineFormExecutor
{
    public function __construct(private PDO $pdo) {}

    public function execute(array $payload): string
    {
        $outer = $_POST;
        $_POST = $payload;
        try {
            return \ygo_handle_post($this->pdo) ?: 'offline_guardado';
        } finally {
            $_POST = $outer;
        }
    }
}
