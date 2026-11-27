<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;
use RuntimeException;
use StationX\Yugioh\Domain\Competitions\RoundRobinPairings;

/** Pure planner for adding missing round-robin fixtures without replacing history. */
final class LeagueExpansionPlan
{
    public static function missingFixtures(
        array $deckIds,
        array $existingPairs,
        int $lastRound,
    ): array {
        $deckIds = array_values(array_map('intval', $deckIds));
        $existing = array_fill_keys($existingPairs, true);
        $fixtures = [];
        $nextRound = $lastRound;

        foreach (RoundRobinPairings::rounds($deckIds, true) as $pairs) {
            $missing = [];
            foreach ($pairs as $pair) {
                $deckA = $deckIds[(int) $pair[0]];
                $deckB = $deckIds[(int) $pair[1]];
                if (!isset($existing[$deckA . ':' . $deckB])) {
                    $missing[] = [$deckA, $deckB];
                }
            }
            if (!$missing) {
                continue;
            }
            $nextRound++;
            foreach ($missing as $table => [$deckA, $deckB]) {
                $fixtures[] = [
                    'round_number' => $nextRound,
                    'table_number' => $table + 1,
                    'deck_a_id' => $deckA,
                    'deck_b_id' => $deckB,
                ];
            }
        }
        return $fixtures;
    }

    public static function schedule(
        array $fixtures,
        string $startDate,
        string $endDate,
        array &$occupiedTimes,
        array &$busyDecks,
        array &$dailyCounts,
    ): array {
        $cursor = new DateTimeImmutable($startDate);
        foreach ($fixtures as &$fixture) {
            while (true) {
                $date = $cursor->format('Y-m-d');
                if ($date > $endDate) {
                    throw new RuntimeException(
                        'La ampliacion de liga excede el cierre de temporada.',
                    );
                }
                $deckA = (int) $fixture['deck_a_id'];
                $deckB = (int) $fixture['deck_b_id'];
                $time = self::freeTime($occupiedTimes[$date] ?? []);
                if (
                    ($dailyCounts[$date] ?? 0) < 3 &&
                    $time !== null &&
                    !isset($busyDecks[$date][$deckA]) &&
                    !isset($busyDecks[$date][$deckB])
                ) {
                    $fixture['scheduled_on'] = $date;
                    $fixture['starts_at'] = $time;
                    $occupiedTimes[$date][$time] = true;
                    $busyDecks[$date][$deckA] = true;
                    $busyDecks[$date][$deckB] = true;
                    $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
                    $cursor = $cursor->modify('+1 day');
                    break;
                }
                $cursor = $cursor->modify('+1 day');
            }
        }
        unset($fixture);
        return $fixtures;
    }

    private static function freeTime(array $occupied): ?string
    {
        foreach (['19:00:00', '20:00:00', '21:00:00'] as $time) {
            if (!isset($occupied[$time])) {
                return $time;
            }
        }
        return null;
    }
}
