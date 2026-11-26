<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class ParticipantCheckinInput
{
    public static function normalize(array $post): ?array
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
        if ($displayName === '') {
            return null;
        }
        return [
            'competitionId' => $competitionId,
            'matchId' => $matchId,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'displayName' => $displayName,
            'code' => $checkinCode,
            'source' => $checkinCode !== '' ? 'qr' : 'manual',
        ];
    }
}
