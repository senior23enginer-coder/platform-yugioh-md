<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native advance_competition adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class AdvanceCompetitionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $competitionId = (int) ($post['competition_id'] ?? 0);
        return 'evento_avanzado_' .
            $competitionId .
            '_' .
            ygo_advance_competition_phase($pdo, $competitionId);

        return null;
    }
}
