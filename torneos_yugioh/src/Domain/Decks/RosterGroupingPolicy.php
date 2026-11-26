<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

final class RosterGroupingPolicy
{
    public static function normalize(array $ids): array
    {
        return array_values(
            array_unique(
                array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0),
            ),
        );
    }

    public static function group(
        array $selectedDeckIds,
        int $participantsPerSide,
        array $rows,
    ): array {
        $teamByDeck = [];
        foreach ($rows as $row) {
            $teamByDeck[(int) $row['id']] = (int) $row['team_id'];
        }
        $teams = [];
        foreach ($selectedDeckIds as $deckId) {
            $teamId = $teamByDeck[$deckId] ?? 0;
            if ($teamId <= 0) {
                continue;
            }
            $teams[$teamId] = $teams[$teamId] ?? [];
            $teams[$teamId][] = $deckId;
        }
        $groups = [];
        foreach ($teams as $teamDecks) {
            if (count($teamDecks) >= $participantsPerSide) {
                $groups[] = array_slice($teamDecks, 0, $participantsPerSide);
            }
        }
        return $groups;
    }
}
