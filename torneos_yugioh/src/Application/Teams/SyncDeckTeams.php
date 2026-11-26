<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

use StationX\Yugioh\Domain\Teams\TeamProfile;

final readonly class SyncDeckTeams
{
    public function __construct(private DeckTeamStore $decks, private FindOrCreateTeam $teams) {}

    public function execute(): int
    {
        $rows = $this->decks->activeDecks();
        $updated = 0;
        foreach ($rows as $row) {
            if (!empty($row['team_id'])) {
                continue;
            }
            $profile = TeamProfile::forDeck(
                (string) $row['name'],
                (string) ($row['archetype'] ?? ''),
            );
            $teamId = $this->teams->execute($profile['name'], $profile);
            $this->decks->assign((int) $row['id'], $teamId);
            $updated++;
        }
        $this->decks->archiveUnusedLegacyTeams();
        return $updated;
    }
}
