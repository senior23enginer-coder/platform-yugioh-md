<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;

/** Native save_checkin adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveCheckinHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $competitionId = max(0, (int) ($post['checkin_competition_id'] ?? 0));
        $matchId = max(0, (int) ($post['checkin_match_id'] ?? 0));
        $entityType = strtolower(trim((string) ($post['checkin_entity_type'] ?? 'guest')));
        $entityType = in_array($entityType, ['deck', 'team', 'player', 'judge', 'guest'], true)
            ? $entityType
            : 'guest';
        $entityId = max(0, (int) ($post['checkin_entity_id'] ?? 0));
        $displayName = mb_substr(trim((string) ($post['checkin_display_name'] ?? '')), 0, 160);
        $checkinCode = mb_substr(trim((string) ($post['checkin_code'] ?? '')), 0, 80);
        if ($displayName !== '') {
            $pdo->prepare(
                'INSERT INTO sx_ygo_checkins (competition_id, match_id, entity_type, entity_id, display_name, checkin_code, source) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE checked_in_at=CURRENT_TIMESTAMP, checkin_code=VALUES(checkin_code), source=VALUES(source)',
            )->execute([
                $competitionId ?: null,
                $matchId ?: null,
                $entityType,
                $entityId ?: null,
                $displayName,
                $checkinCode ?: null,
                $checkinCode !== '' ? 'qr' : 'manual',
            ]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )->execute([
                'checkin',
                $matchId ?: $competitionId,
                'participant_checkin',
                json_encode(
                    [
                        'competition_id' => $competitionId,
                        'match_id' => $matchId,
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'display_name' => $displayName,
                    ],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
        }
        return 'estadisticas';

        return null;
    }
}
