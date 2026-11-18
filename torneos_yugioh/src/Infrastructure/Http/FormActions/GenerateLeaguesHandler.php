<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native generate_leagues adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class GenerateLeaguesHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return 'ligas_' . ygo_generate_leagues($pdo);

        return null;
    }
}
