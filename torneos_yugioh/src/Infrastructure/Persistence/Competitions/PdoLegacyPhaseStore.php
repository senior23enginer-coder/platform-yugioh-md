<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\LegacyPhaseStore;

final readonly class PdoLegacyPhaseStore implements LegacyPhaseStore
{
    public function __construct(private PDO $pdo) {}

    public function firstDivisionId(): int
    {
        return \ygo_first_division_id($this->pdo);
    }

    public function finishedResults(string $phase): array
    {
        $rows = $this->pdo->prepare("
            SELECT m.division_id, m.phase, m.round_number, m.table_number, m.deck_a_id, m.deck_b_id, r.result
            FROM sx_ygo_matches m
            JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.phase = ? AND m.status = 'FINALIZADA'
            ORDER BY m.division_id, m.round_number, m.table_number
        ");
        $rows->execute([$phase]);
        return $rows->fetchAll(PDO::FETCH_ASSOC);
    }

    public function nextRound(int $divisionId, string $phase): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(round_number), 0) + 1 FROM sx_ygo_matches WHERE division_id = ? AND phase = ?');
        $stmt->execute([$divisionId, $phase]);
        return (int) $stmt->fetchColumn();
    }

    public function insert(int $divisionId, string $phase, int $round, iterable $rows): int
    {
        $created = 0;
        foreach ($rows as $row) {
            $this->pdo->prepare("
                INSERT IGNORE INTO sx_ygo_matches (division_id, phase, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, 'PENDIENTE')
            ")->execute([$divisionId, $phase, $round, $row['slot'], $row['label'], $row['slot'], $row['displayOrder'], $row['deckA'], $row['deckB']]);
            $created++;
        }
        return $created;
    }
}
