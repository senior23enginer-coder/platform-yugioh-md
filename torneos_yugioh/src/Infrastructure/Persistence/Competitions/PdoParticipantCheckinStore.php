<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\ParticipantCheckinStore;

final readonly class PdoParticipantCheckinStore implements ParticipantCheckinStore
{
    public function __construct(private PDO $pdo) {}

    public function save(array $input): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_checkins (competition_id, match_id, entity_type, entity_id, display_name, checkin_code, source) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE checked_in_at=CURRENT_TIMESTAMP, checkin_code=VALUES(checkin_code), source=VALUES(source)')
            ->execute([$input['competitionId'] ?: null, $input['matchId'] ?: null, $input['entityType'], $input['entityId'] ?: null, $input['displayName'], $input['code'] ?: null, $input['source']]);
    }

    public function audit(array $input): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['checkin', $input['matchId'] ?: $input['competitionId'], 'participant_checkin', json_encode([
                'competition_id' => $input['competitionId'],
                'match_id' => $input['matchId'],
                'entity_type' => $input['entityType'],
                'entity_id' => $input['entityId'],
                'display_name' => $input['displayName'],
            ], JSON_UNESCAPED_UNICODE)]);
    }
}
