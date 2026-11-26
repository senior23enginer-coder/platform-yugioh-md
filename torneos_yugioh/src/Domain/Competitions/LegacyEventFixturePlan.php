<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class LegacyEventFixturePlan
{
    public static function league(array $deckRows): iterable
    {
        $fixture = FixtureBuilder::build(
            array_map(
                static fn(array $deck): array => [
                    (int) $deck['seed_rank'],
                    (string) $deck['name'],
                    (string) ($deck['reason'] ?? ''),
                ],
                $deckRows,
            ),
        );
        $deckIdByName = [];
        foreach ($deckRows as $deck) {
            $deckIdByName[(string) $deck['name']] = (int) $deck['id'];
        }
        foreach ($fixture as $round) {
            foreach ($round['matches'] as $match) {
                if (!isset($deckIdByName[$match['a']], $deckIdByName[$match['b']])) {
                    continue;
                }
                yield [
                    $round['ronda'],
                    $match['mesa'],
                    $deckIdByName[$match['a']],
                    $deckIdByName[$match['b']],
                ];
            }
        }
    }

    public static function knockout(array $decks): iterable
    {
        $created = 0;
        $count = count($decks);
        if ($count < 2) {
            return;
        }
        $pairs = intdiv($count, 2);
        for ($i = 0; $i < $pairs; $i++) {
            $a = $decks[$i];
            $b = $decks[$count - 1 - $i];
            yield [$i + 1, 'Ronda 1', $i + 1, $i + 1, (int) $a['id'], (int) $b['id']];
            $created++;
        }
    }

    public static function friendlies(array $decks): iterable
    {
        $createdPairs = [];
        $created = 0;
        $count = count($decks);
        foreach ($decks as $index => $deck) {
            foreach ([1, 5, 11, 17, 23] as $roundIndex => $offset) {
                $rival = $decks[($index + $offset) % $count];
                if ((int) $deck['id'] === (int) $rival['id']) {
                    continue;
                }
                $pairKey = implode('-', [
                    min((int) $deck['id'], (int) $rival['id']),
                    max((int) $deck['id'], (int) $rival['id']),
                ]);
                if (isset($createdPairs[$pairKey])) {
                    continue;
                }
                $createdPairs[$pairKey] = true;
                yield [$roundIndex + 1, $created + 1, (int) $deck['id'], (int) $rival['id']];
                $created++;
            }
        }
    }

    public static function world(array $decks): iterable
    {
        $created = 0;
        for ($group = 0; $group < 6; $group++) {
            $groupDecks = array_slice($decks, $group * 4, 4);
            for ($i = 0; $i < count($groupDecks); $i++) {
                for ($j = $i + 1; $j < count($groupDecks); $j++) {
                    yield [
                        $group + 1,
                        $created + 1,
                        (int) $groupDecks[$i]['id'],
                        (int) $groupDecks[$j]['id'],
                    ];
                    $created++;
                }
            }
        }
    }
}
