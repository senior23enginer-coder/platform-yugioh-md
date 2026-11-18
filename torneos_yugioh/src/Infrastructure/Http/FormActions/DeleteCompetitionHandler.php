<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native delete_competition adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class DeleteCompetitionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        return 'evento_eliminado_' . ygo_delete_competition($pdo);

        return null;
    }
}
