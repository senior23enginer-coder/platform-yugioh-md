<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

use StationX\Yugioh\Domain\Competitions\FixtureBuilder;

final class SeedDataProjection
{
    public static function fixturesAndDecks(array $divisions): array
    {
        $fixtures = [];
        $allDecks = [];
        foreach ($divisions as $key => $division) {
            $fixtures[$key] = FixtureBuilder::build($division['decks']);
            foreach ($division['decks'] as $deck) {
                $allDecks[] = [
                    'rank' => $deck[0],
                    'name' => $deck[1],
                    'division' => $key,
                    'reason' => $deck[2],
                ];
            }
        }
        return ['fixtures' => $fixtures, 'allDecks' => $allDecks];
    }

    public static function labels(array $defaults): array
    {
        $labels = [];
        foreach ($defaults as $label) {
            $labels[$label[0]] = [
                'id' => 0,
                'key' => $label[0],
                'title' => $label[1],
                'subtitle' => $label[2],
                'active' => 1,
            ];
        }
        return $labels;
    }

    public static function labelText(
        array $labels,
        string $key,
        string $field,
        string $fallback,
    ): string {
        if (isset($labels[$key]['active']) && (int) $labels[$key]['active'] !== 1) {
            return $fallback;
        }
        $value = trim((string) ($labels[$key][$field] ?? ''));
        return $value !== '' ? $value : $fallback;
    }
}
