<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** HTTP adapter delegating to the application command. */
final class ApplyQualifierDivisionsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return \StationX\Yugioh\Infrastructure\Composition\CompetitionMaintenanceModule::competitions(
            $pdo,
        )->qualifierDivisions($post);
    }
}
