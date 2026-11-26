<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Compatibility adapter: delegates to a port-based application use case. */
final class SaveTeamHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return \StationX\Yugioh\Infrastructure\Composition\TeamManagementModule::team(
            $pdo,
        )->execute($post);
    }
}
