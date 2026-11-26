<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class DirectoryProjection
{
    public static function divisions(array $divisions): array
    {
        $visible = array_filter(
            $divisions,
            static fn(array $division): bool => (int) ($division['id'] ?? 0) > 0 &&
                (int) ($division['active'] ?? 1) === 1,
        );
        $unique = [];
        foreach ($visible as $key => $division) {
            $identity = (string) (($division['code'] ?? '') ?: ($division['short'] ?? '') ?: $key);
            if ($identity === '') {
                $identity = (string) $key;
            }
            if (!isset($unique[$identity])) {
                $unique[$identity] = $division;
            }
        }
        return $unique;
    }

    public static function deckTeams(array $decks): array
    {
        $teams = [];
        foreach ($decks as $deck) {
            $teams[(string) ($deck['name'] ?? '')] = (string) (($deck['team'] ?? '') ?: 'Sin equipo');
        }
        return $teams;
    }

    public static function matchLabel(array $teams, string $deck): string
    {
        return (($teams[$deck] ?? 'Sin equipo') ?: 'Sin equipo') . ' / ' . $deck;
    }
}
