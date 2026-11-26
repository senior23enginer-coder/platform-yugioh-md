<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\KnockoutRoundStore;

final readonly class PdoKnockoutRoundStore implements KnockoutRoundStore
{
    public function __construct(private PDO $pdo) {}

    public function exists(int $competitionId, string $phase, int $round): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ? AND round_number = ?');
        $stmt->execute([$competitionId, $phase, $round]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function insert(iterable $rows): int
    {
        $insert = $this->pdo->prepare('
            INSERT INTO sx_ygo_matches
                (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status, scheduled_on, starts_at, venue, notes)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "main", ?, 1, ?, ?, "PENDIENTE", ?, "19:00:00", "Station X Tulua", ?)
        ');
        $created = 0;
        foreach ($rows as $row) {
            $insert->execute([
                $row['divisionId'],
                $row['competitionId'],
                $row['gameRuleId'],
                $row['phase'],
                $row['matchFormat'],
                $row['bestOf'],
                $row['participantsPerSide'],
                $row['round'],
                $row['table'],
                $row['label'],
                $row['slot'],
                $row['displayOrder'],
                $row['deckA'],
                $row['deckB'],
                $row['date'],
                $row['notes'],
            ]);
            $created++;
        }
        return $created;
    }

    public function auditBye(int $competitionId, int $round, int $deckId): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')
            ->execute(['competition', $competitionId, 'cup_round_bye', json_encode(['round' => $round, 'deck_id' => $deckId])]);
    }
}
