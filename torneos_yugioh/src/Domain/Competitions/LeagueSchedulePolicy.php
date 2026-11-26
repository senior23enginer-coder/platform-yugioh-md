<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class LeagueSchedulePolicy
{
    public static function validateMembership(array $ids, array $owned, array $deckDivisions): void
    {
        foreach ($ids as $id) {
            if (!isset($deckDivisions[$id]) || !isset($owned[(int) $deckDivisions[$id]])) {
                throw new InvalidArgumentException(
                    'Asigna cada participante a una division de esta liga antes de generar sus combates.',
                );
            }
        }
    }

    public static function plan(array $groupsByDivision, bool $doubleRound): array
    {
        $groups = [];
        $rounds = [];
        foreach ($groupsByDivision as $divisionId => $localGroups) {
            if (!$localGroups) {
                continue;
            }
            self::requireCompleteGroups($localGroups);
            $offset = count($groups);
            $groups = array_merge($groups, $localGroups);
            foreach (
                RoundRobinPairings::rounds($localGroups, $doubleRound)
                as $roundIndex => $pairs
            ) {
                foreach ($pairs as $tableIndex => $pair) {
                    $rounds[$roundIndex][] = [
                        $offset + $pair[0],
                        $offset + $pair[1],
                        (int) $divisionId,
                        $tableIndex + 1,
                    ];
                }
            }
        }
        ksort($rounds);
        return ['groups' => $groups, 'rounds' => array_values($rounds)];
    }

    public static function requireCompleteGroups(array $groups): void
    {
        if (count($groups) < 2) {
            throw new InvalidArgumentException(
                'Cada division con participantes necesita al menos dos lados completos para generar combates.',
            );
        }
    }
}
