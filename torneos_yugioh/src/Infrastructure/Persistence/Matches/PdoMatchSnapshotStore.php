<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\MatchSnapshotStore;

final readonly class PdoMatchSnapshotStore implements MatchSnapshotStore
{
    public function __construct(private PDO $pdo) {}

    public function match(int $matchId): array
    {
        $matchSnapshotStmt = $this->pdo->prepare('
            SELECT m.competition_id, m.division_id, m.deck_a_id, m.deck_b_id,
                   c.event_ruleset, c.ruleset_version, dv.name AS division_name
            FROM sx_ygo_matches m
            LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
            LEFT JOIN sx_ygo_divisions dv ON dv.id = COALESCE(m.division_id, c.division_id)
            WHERE m.id = ? LIMIT 1
        ');
        $matchSnapshotStmt->execute([$matchId]);
        return $matchSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function deck(int $deckId): array
    {
        $deckSnapshotStmt = $this->pdo->prepare('
            SELECT d.id, d.team_id, d.name, d.archetype, d.variations,
                   t.name AS team_name
            FROM sx_ygo_decks d
            LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
            WHERE d.id = ? LIMIT 1
        ');
        $deckSnapshotStmt->execute([$deckId]);
        return $deckSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function decklist(int $deckId): array
    {
        $decklistSnapshotStmt = $this->pdo->prepare('
            SELECT card_name, role, quantity, section, notes
            FROM sx_ygo_deck_cards WHERE deck_id = ? ORDER BY section, id
        ');
        $decklistSnapshotStmt->execute([$deckId]);
        return $decklistSnapshotStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $row): void
    {
        $insertSnapshotStmt = $this->pdo->prepare('
            INSERT IGNORE INTO sx_ygo_match_snapshots
                (match_id, duel_number, side, competition_id, deck_id, team_id, player_id,
                 deck_name, archetype, variation, team_name, ruleset_code, ruleset_version,
                 division_name, decklist_json, decklist_hash, data_origin, snapshot_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insertSnapshotStmt->execute([
            $row['matchId'],
            $row['duel'],
            $row['side'],
            $row['competitionId'],
            $row['deckId'],
            $row['teamId'],
            $row['playerId'],
            $row['deckName'],
            $row['archetype'],
            $row['variation'],
            $row['teamName'],
            $row['rulesetCode'],
            $row['rulesetVersion'],
            $row['divisionName'],
            $row['decklistJson'],
            $row['decklistHash'],
            $row['dataOrigin'],
            $row['snapshotStatus'],
        ]);
    }
}
