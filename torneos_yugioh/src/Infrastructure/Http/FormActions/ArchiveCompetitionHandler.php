<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native archive_competition adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class ArchiveCompetitionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $id = (int) ($post['competition_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare(
                "UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE id=?",
            )->execute([$id]);
        }
        return 'evento_archivado';

        return null;
    }
}
