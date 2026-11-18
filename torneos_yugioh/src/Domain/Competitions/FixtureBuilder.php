<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

/** Pure competition policy. No PDO, HTTP, clock or session dependencies. */
final class FixtureBuilder
{
    /** Preserve the established round order, bye handling and participant identities. */
    public static function build(array $decks): array
    {
        $names = array_map(static fn(array $deck): string => $deck[1], $decks);
        if (count($names) % 2 === 1) {
            $names[] = 'DESCANSO';
        }
        $rounds = count($names) - 1;
        $half = count($names) / 2;
        $rotation = $names;
        $fixture = [];

        for ($round = 1; $round <= $rounds; $round++) {
            $matches = [];
            $rest = '';
            for ($i = 0; $i < $half; $i++) {
                $a = $rotation[$i];
                $b = $rotation[count($rotation) - 1 - $i];
                if ($a === 'DESCANSO' || $b === 'DESCANSO') {
                    $rest = $a === 'DESCANSO' ? $b : $a;
                    continue;
                }
                $matches[] = ['mesa' => count($matches) + 1, 'a' => $a, 'b' => $b, 'estado' => 'PENDIENTE'];
            }
            $fixture[] = ['ronda' => $round, 'descansa' => $rest, 'matches' => $matches];
            $fixed = array_shift($rotation);
            $last = array_pop($rotation);
            array_unshift($rotation, $fixed, $last);
        }
        return $fixture;
    }
}
