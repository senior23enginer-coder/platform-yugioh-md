<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

final class LegacyQualifierPairings
{
    public static function build(array $deckIds): array
    {
        $players = $deckIds;
        if (count($players) % 2 === 1) {
            $players[] = 0;
        }
        $opponents = [];
        $played = [];
        foreach ($deckIds as $deckId) {
            $opponents[$deckId] = [];
            $played[$deckId] = 0;
        }
        $pairs = [];
        $roundCount = 3;
        $n = count($players);
        for ($round = 1; $round <= $roundCount; $round++) {
            $table = 1;
            for ($i = 0; $i < $n / 2; $i++) {
                $a = (int) $players[$i];
                $b = (int) $players[$n - 1 - $i];
                if ($a <= 0 || $b <= 0 || $a === $b) {
                    continue;
                }
                $key = min($a, $b) . '-' . max($a, $b);
                if (isset($pairs[$key])) {
                    continue;
                }
                $pairs[$key] = [$round, $table++, $a, $b];
                $opponents[$a][$b] = true;
                $opponents[$b][$a] = true;
                $played[$a]++;
                $played[$b]++;
            }
            $fixed = array_shift($players);
            $last = array_pop($players);
            array_unshift($players, $fixed, $last);
        }

        $table = 1;
        while (count(array_filter($played, static fn(int $count): bool => $count < 3)) > 0) {
            $needs = array_keys(array_filter($played, static fn(int $count): bool => $count < 3));
            $a = (int) array_shift($needs);
            $b = 0;
            foreach (array_merge($needs, $deckIds) as $candidate) {
                $candidate = (int) $candidate;
                if ($candidate !== $a && empty($opponents[$a][$candidate])) {
                    $b = $candidate;
                    break;
                }
            }
            if ($b <= 0) {
                break;
            }
            $key = min($a, $b) . '-' . max($a, $b);
            $pairs[$key] = [4, $table++, $a, $b];
            $opponents[$a][$b] = true;
            $opponents[$b][$a] = true;
            $played[$a]++;
            $played[$b]++;
        }

        uasort($pairs, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        return array_values($pairs);
    }
}
