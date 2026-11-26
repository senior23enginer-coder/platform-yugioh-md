<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** HTTP adapter for the versioned deck analysis use case. */
final class SaveMatchupAnalysisHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return \StationX\Yugioh\Infrastructure\Composition\DeckAnalysisModule::matchup($pdo)->execute($post);
    }
}
