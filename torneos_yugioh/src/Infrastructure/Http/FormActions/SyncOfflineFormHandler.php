<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** HTTP adapter for the application command. */
final class SyncOfflineFormHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return \StationX\Yugioh\Infrastructure\Composition\CommandMaintenanceModule::offline(
            $pdo,
        )->execute($post);
    }
}
