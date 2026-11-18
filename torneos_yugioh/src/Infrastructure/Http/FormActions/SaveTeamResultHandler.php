<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_team_result adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveTeamResultHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $rows = ygo_team_decode_payload((string) ($post['team_sequences_json'] ?? '[]'));
        $mid = (int) ($post['match_id'] ?? 0);
        $c = ygo_competition_for_match($pdo, $mid);
        ygo_team_save_result($pdo, $mid, $rows, (int) ($post['team_revision'] ?? 0));
        return 'evento_configurado_' . (int) ($c['id'] ?? 0);

        return null;
    }
}
