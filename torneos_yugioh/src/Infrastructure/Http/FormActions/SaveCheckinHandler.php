<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** HTTP adapter for the application use case. */
final class SaveCheckinHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return \StationX\Yugioh\Infrastructure\Composition\CompetitionAdministrativeModule::checkin(
            $pdo,
        )->execute($post);
    }
}
