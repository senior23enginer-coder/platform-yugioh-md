<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\LegacyEventFixtureWriter;

final readonly class PdoLegacyEventFixtureWriter implements LegacyEventFixtureWriter
{
    public function __construct(private PDO $pdo) {}

    public function clearPhase(int $divisionId, string $phase): void
    {
        $this->pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.division_id = ? AND m.phase = ?')->execute([$divisionId, $phase]);
        $this->pdo->prepare('DELETE FROM sx_ygo_matches WHERE division_id = ? AND phase = ?')->execute([$divisionId, $phase]);
    }

    public function insert(int $divisionId, string $phase, iterable $rows): int
    {
        $created = 0;
        foreach ($rows as [$round, $table, $deckA, $deckB]) {
            $this->pdo->prepare("
                INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'PENDIENTE')
            ")->execute([$divisionId, $phase, $round, $table, $deckA, $deckB]);
            $created++;
        }
        return $created;
    }

    public function insertKnockout(int $divisionId, string $phase, iterable $rows): int
    {
        $created = 0;
        foreach ($rows as $row) {
            $this->pdo->prepare("
                INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status)
                VALUES (?, ?, 1, ?, ?, ?, 'main', ?, 1, ?, ?, 'PENDIENTE')
            ")->execute([$divisionId, $phase, ...$row]);
            $created++;
        }
        return $created;
    }
}
