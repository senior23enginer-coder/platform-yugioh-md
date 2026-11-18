<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native advance_phase adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class AdvancePhaseHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $phase = preg_replace('/[^a-z_]/', '', strtolower((string) ($post['phase'] ?? 'cup')));
        if ($phase === '') {
            $phase = 'cup';
        }
        $competitionId = (int) ($post['competition_id'] ?? 0);
        return 'avance_' .
            $phase .
            '_' .
            ygo_advance_phase($pdo, $phase, $competitionId > 0 ? $competitionId : null);

        return null;
    }
}
