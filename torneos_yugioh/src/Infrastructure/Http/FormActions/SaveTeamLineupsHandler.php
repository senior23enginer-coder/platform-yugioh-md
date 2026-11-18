<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_team_lineups adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveTeamLineupsHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $rows = ygo_team_decode_payload((string) ($post['team_lineups_json'] ?? '[]'));
        $id = (int) ($post['competition_id'] ?? 0);
        ygo_team_save($pdo, $id, $rows);
        return 'evento_configurado_' . $id;

        return null;
    }
}
