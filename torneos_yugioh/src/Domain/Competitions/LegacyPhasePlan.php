<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class LegacyPhasePlan
{
    public static function winnersByDivision(array $rows): array
    {
        $winnersByDivision = [];
        foreach ($rows as $row) {
            $winner =
                $row['result'] === 'A'
                    ? (int) $row['deck_a_id']
                    : ($row['result'] === 'B'
                        ? (int) $row['deck_b_id']
                        : 0);
            if ($winner > 0) {
                $winnersByDivision[(int) $row['division_id']][] = $winner;
            }
        }
        return $winnersByDivision;
    }

    public static function rows(array $winners, int $nextRound): iterable
    {
        for ($i = 0; $i + 1 < count($winners); $i += 2) {
            $slot = intdiv($i, 2) + 1;
            yield [
                'slot' => $slot,
                'label' => 'Ronda ' . $nextRound,
                'displayOrder' => ($nextRound - 1) * 100 + $slot,
                'deckA' => $winners[$i],
                'deckB' => $winners[$i + 1],
            ];
        }
    }
}
