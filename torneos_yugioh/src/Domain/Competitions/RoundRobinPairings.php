<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

/** Pure competition policy. No PDO, HTTP, clock or session dependencies. */
final class RoundRobinPairings
{
    /** Preserve the established round order, bye handling and participant identities. */
    public static function rounds(array $groups, bool $doubleRound = false): array
    {
        $count = count($groups);
        if ($count < 2) {
            return [];
        }
        $indexes = range(0, $count - 1);
        if ($count % 2 === 1) {
            $indexes[] = null;
        }
        $size = count($indexes);
        $rounds = [];
        for ($round = 1; $round < $size; $round++) {
            $pairs = [];
            for ($i = 0; $i < (int) ($size / 2); $i++) {
                $left = $indexes[$i];
                $right = $indexes[$size - 1 - $i];
                if ($left === null || $right === null) {
                    continue;
                }
                $pairs[] = [$left, $right];
            }
            $rounds[] = $pairs;
            $fixed = array_shift($indexes);
            $tail = array_pop($indexes);
            array_unshift($indexes, $fixed);
            array_splice($indexes, 1, 0, [$tail]);
        }
        if ($doubleRound) {
            foreach ($rounds as $pairs) {
                $rounds[] = array_map(
                    static fn(array $pair): array => [$pair[1], $pair[0]],
                    $pairs,
                );
            }
        }
        return $rounds;
    }
}
