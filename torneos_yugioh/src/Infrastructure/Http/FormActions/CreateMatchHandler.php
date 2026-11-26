<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** HTTP adapter for manual match creation. */
final class CreateMatchHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return \StationX\Yugioh\Infrastructure\Composition\ManualMatchModule::create($pdo)->execute($post);
    }
}
