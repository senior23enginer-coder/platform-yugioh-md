<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;
use StationX\Yugioh\Domain\Competitions\RoundRobinPairings;

final class FixtureGenerationPlan
{
    public static function rounds(
        array $groups,
        ?array $leaguePlan,
        string $execution,
        string $phase,
    ): array {
        $phaseStructure = 'NONE';
        if ($leaguePlan!==null) {
            $groups=$leaguePlan['groups'];
            $rounds=$leaguePlan['rounds'];
        } elseif (in_array($execution, ['groups', 'groups_knockout'], true) || str_contains($phaseStructure, 'GROUPS')) {
            $rounds = [];
            $chunks = array_chunk(range(0, count($groups) - 1), max(2, (int) ceil(count($groups) / 2)));
            foreach ($chunks as $chunk) {
                $localGroups = array_map(static fn(int $index): array => $groups[$index], $chunk);
                $localRounds = RoundRobinPairings::rounds($localGroups, false);
                foreach ($localRounds as $roundPairs) {
                    $rounds[] = array_map(static function (array $pair) use ($chunk): array {
                        return [$chunk[$pair[0]], $chunk[$pair[1]]];
                    }, $roundPairs);
                }
            }
            $phase = 'groups';
        } else {
            $rounds = RoundRobinPairings::rounds($groups, $execution === 'double_round_robin');
        }
        return ['groups' => $groups, 'rounds' => $rounds, 'phase' => $phase];
    }

    public static function cupRows(
        array $groups,
        ?int $divisionId,
        int $competitionId,
        array $competition,
        string $startDate,
        string $pattern,
        string $matchFormat,
        int $participantsPerSide,
        int $matchesPerDay,
        int $generation,
    ): iterable {
        $groupCount = count($groups);
        $start = new DateTimeImmutable($startDate);
        $pairs = intdiv($groupCount, 2);
        $created = 0;
        for ($i = 0; $i < $pairs; $i++) {
            $leftIndex = $i;
            $rightIndex = $groupCount - 1 - $i;
            if (($generation + $i) % 2 === 0) {
                [$leftIndex, $rightIndex] = [$rightIndex, $leftIndex];
            }
            $sideA = array_pad($groups[$leftIndex], 3, null);
            $sideB = array_pad($groups[$rightIndex], 3, null);
            $scheduledOn = MatchDatePattern::date($start, $pattern, $created, $matchesPerDay);
            $slot = $created % $matchesPerDay;
            $startsAt = (new DateTimeImmutable('19:00'))->modify('+' . ($slot * 45) . ' minutes')->format('H:i:s');
            yield [
                'division_id' => $divisionId,
                'competition_id' => $competitionId,
                'match_format' => $matchFormat,
                'best_of' => (int) ($competition['best_of'] ?? 3),
                'participants_per_side' => $participantsPerSide,
                'table_number' => $created + 1,
                'bracket_slot' => $created + 1,
                'display_order' => $created + 1,
                'generation_number' => $generation,
                'deck_a_id' => $sideA[0],
                'deck_b_id' => $sideB[0],
                'deck_c_id' => $sideA[1],
                'deck_d_id' => $sideB[1],
                'deck_e_id' => $sideA[2],
                'deck_f_id' => $sideB[2],
                'scheduled_on' => $scheduledOn,
                'starts_at' => $startsAt,
                'notes' => 'Generado por algoritmo de copa. Generacion ' . $generation . '. Duelos por dia: ' . $matchesPerDay . '.',
            ];
            $created++;
        }
    }

    public static function modelRows(
        array $groups,
        array $rounds,
        int $divisionId,
        int $competitionId,
        array $competition,
        string $startDate,
        string $pattern,
        string $matchFormat,
        int $participantsPerSide,
        int $matchesPerDay,
        int $generation,
        string $phase,
        string $execution,
    ): iterable {
        $start = new DateTimeImmutable($startDate);
        $created = 0;
        foreach ($rounds as $roundIndex => $pairs) {
            foreach ($pairs as $tableIndex => $pair) {
                $sideA = array_pad($groups[(int) $pair[0]], 3, null);
                $sideB = array_pad($groups[(int) $pair[1]], 3, null);
                $scheduledOn = MatchDatePattern::date($start, $pattern, $created, $matchesPerDay);
                $slot = $created % $matchesPerDay;
                $startsAt = (new DateTimeImmutable('19:00'))->modify('+' . ($slot * 45) . ' minutes')->format('H:i:s');
                yield [
                    'division_id' => (int) ($pair[2] ?? $divisionId),
                    'competition_id' => $competitionId,
                    'phase' => $phase,
                    'match_format' => $matchFormat,
                    'best_of' => (int) ($competition['best_of'] ?? 3),
                    'participants_per_side' => $participantsPerSide,
                    'round_number' => $roundIndex + 1,
                    'table_number' => (int) ($pair[3] ?? ($tableIndex + 1)),
                    'bracket_round_label' => 'Ronda ' . ($roundIndex + 1),
                    'bracket_slot' => (int) ($pair[3] ?? ($tableIndex + 1)),
                    'display_order' => $created + 1,
                    'generation_number' => $generation,
                    'deck_a_id' => $sideA[0],
                    'deck_b_id' => $sideB[0],
                    'deck_c_id' => $sideA[1],
                    'deck_d_id' => $sideB[1],
                    'deck_e_id' => $sideA[2],
                    'deck_f_id' => $sideB[2],
                    'scheduled_on' => $scheduledOn,
                    'starts_at' => $startsAt,
                    'notes' => 'Generado por modelo ' . $execution . '. Generacion ' . $generation . '.',
                ];
                $created++;
            }
        }
    }
}
