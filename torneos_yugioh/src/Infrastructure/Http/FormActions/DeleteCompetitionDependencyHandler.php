<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native delete_competition_dependency adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class DeleteCompetitionDependencyHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $dependencyId = (int) ($post['dependency_id'] ?? 0);
        if ($dependencyId > 0) {
            $pdo->prepare(
                'UPDATE sx_ygo_competition_dependencies SET active=0 WHERE id=?',
            )->execute([$dependencyId]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute(['competition_dependency', $dependencyId, 'delete_dependency', '{}']);
        }
        return 'dependencia_quitada';

        return null;
    }
}
