<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native revert_event_stage adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class RevertEventStageHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $competitionId = (int) ($post['competition_id'] ?? 0);
        ygo_workflow_revert(
            $pdo,
            $competitionId,
            (int) ($post['expected_round'] ?? 0),
            (string) ($post['recovery_reason'] ?? ''),
            (string) ($post['recovery_token'] ?? ''),
        );
        return 'evento_configurado_' . $competitionId;

        return null;
    }
}
