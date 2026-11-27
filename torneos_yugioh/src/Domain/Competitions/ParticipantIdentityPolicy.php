<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class ParticipantIdentityPolicy
{
    public static function freeze(array $ids, array $rows, string $format): array
    {
        $out = [];
        foreach ($rows as $d) {
            $out[(int) $d['id']] = [
                'deck_id' => (int) $d['id'],
                'team_id' => (int) ($d['team_id'] ?? 0),
                'format_id' => $format,
                'quality' => 'CONFIRMED_AT_ROSTER_FREEZE',
            ];
        }
        if (count($out) !== count($ids)) {
            throw new InvalidArgumentException('No se pudo fijar la identidad de todos los participantes.');
        }
        return $out;
    }
}
