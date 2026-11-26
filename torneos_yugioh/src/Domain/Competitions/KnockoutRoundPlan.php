<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use DateTimeImmutable;

final class KnockoutRoundPlan
{
    public static function normalize(array $deckIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $deckIds), static fn(int $id): bool => $id > 0)));
    }

    public static function rows(array $competition, string $phase, array $deckIds, int $round, string $label, string $scheduledBase): iterable
    {
        $competitionId = (int) ($competition['id'] ?? 0);
        $divisionId = !empty($competition['division_id']) ? (int) $competition['division_id'] : null;
        $pairs = intdiv(count($deckIds), 2);
        for ($i = 0; $i < $pairs; $i++) {
            $deckA = $deckIds[$i];
            $deckB = $deckIds[count($deckIds) - 1 - $i];
            $displayOrder = ($round * 100) + ($i + 1);
            $scheduledOn = (new DateTimeImmutable($scheduledBase))->modify('+' . max(0, $round - 1) . ' days')->format('Y-m-d');
            yield [
                'divisionId' => $divisionId,
                'competitionId' => $competitionId,
                'gameRuleId' => (int) ($competition['game_rule_id'] ?? 0) ?: null,
                'phase' => $phase,
                'matchFormat' => (string) (($competition['match_format'] ?? '') ?: '1v1'),
                'bestOf' => (int) (($competition['best_of'] ?? 0) ?: 3),
                'participantsPerSide' => max(1, min(3, (int) (($competition['participants_per_side'] ?? 0) ?: 1))),
                'round' => $round,
                'table' => $i + 1,
                'label' => $label,
                'slot' => $i + 1,
                'displayOrder' => $displayOrder,
                'deckA' => $deckA,
                'deckB' => $deckB,
                'date' => $scheduledOn,
                'notes' => 'Generado por avance automatico de ' . $phase . '.',
            ];
        }
    }

    public static function bye(string $phase, array $deckIds): ?int
    {
        return $phase === 'cup' && count($deckIds) % 2 === 1
            ? (int) $deckIds[intdiv(count($deckIds), 2)]
            : null;
    }
}
