<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\DeckTeamStore;

final readonly class PdoDeckTeamStore implements DeckTeamStore
{
    public function __construct(private PDO $pdo) {}

    public function activeDecks(): array
    {
        return $this->pdo
            ->query(
                "
            SELECT id, name, archetype, team_id
            FROM sx_ygo_decks
            WHERE active = 1
            ORDER BY seed_rank, id
        ",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assign(int $deckId, int $teamId): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_decks SET team_id = ? WHERE id = ?')
            ->execute([$teamId, $deckId]);
    }

    public function archiveUnusedLegacyTeams(): void
    {
        $legacyTeamNames = [
            'Station X Titans',
            'Tulua Duel Team',
            'Meta Lab',
            'Rogue Masters',
            'Duel Academy',
        ];
        $archive = $this->pdo->prepare("
            UPDATE sx_ygo_teams t
            SET t.active = 0
            WHERE t.name = ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM sx_ygo_decks d
                  WHERE d.team_id = t.id
                    AND d.active = 1
              )
        ");
        foreach ($legacyTeamNames as $teamName) {
            $archive->execute([$teamName]);
        }
        $this->pdo->exec("
            UPDATE sx_ygo_teams t
            SET t.active = 0
            WHERE t.name LIKE 'Tulu% Duel Team'
              AND NOT EXISTS (
                  SELECT 1
                  FROM sx_ygo_decks d
                  WHERE d.team_id = t.id
                    AND d.active = 1
              )
        ");
        $this->pdo->exec("
            UPDATE sx_ygo_teams t
            SET t.active = 0
            WHERE t.name LIKE 'Equipo %'
              AND NOT EXISTS (
                  SELECT 1
                  FROM sx_ygo_decks d
                  WHERE d.team_id = t.id
                    AND d.active = 1
              )
        ");
    }
}
