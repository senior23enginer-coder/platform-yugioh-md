<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\FixtureGenerationWriter;

final readonly class PdoFixtureGenerationWriter implements FixtureGenerationWriter
{
    public function __construct(private PDO $pdo) {}

    public function clearPending(int $competitionId): void
    {
        $this->pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.competition_id = ? AND m.status <> "FINALIZADA"')->execute([$competitionId]);
        $this->pdo->prepare('DELETE FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"')->execute([$competitionId]);
    }

    public function insertCup(iterable $rows): int
    {
        $insert = $this->pdo->prepare('
            INSERT INTO sx_ygo_matches
                (division_id, competition_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, notes)
            VALUES
                (?, ?, "cup", ?, ?, ?, 1, ?, "Octavos / ronda inicial", ?, "main", ?, ?, ?, ?, ?, ?, ?, ?, "PENDIENTE", ?, ?, "Station X Tulua", ?)
        ');
        $created = 0;
        foreach ($rows as $row) {
            $insert->execute([
                $row['division_id'],
                $row['competition_id'],
                $row['match_format'],
                $row['best_of'],
                $row['participants_per_side'],
                $row['table_number'],
                $row['bracket_slot'],
                $row['display_order'],
                $row['generation_number'],
                $row['deck_a_id'],
                $row['deck_b_id'],
                $row['deck_c_id'],
                $row['deck_d_id'],
                $row['deck_e_id'],
                $row['deck_f_id'],
                $row['scheduled_on'],
                $row['starts_at'],
                $row['notes'],
            ]);
            $created++;
        }
        return $created;
    }

    public function insertModel(iterable $rows): int
    {
        $insert = $this->pdo->prepare('
            INSERT INTO sx_ygo_matches
                (division_id, competition_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, notes)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "main", ?, ?, ?, ?, ?, ?, ?, ?, "PENDIENTE", ?, ?, "Station X Tulua", ?)
        ');
        $created = 0;
        foreach ($rows as $row) {
            $insert->execute([
                $row['division_id'],
                $row['competition_id'],
                $row['phase'],
                $row['match_format'],
                $row['best_of'],
                $row['participants_per_side'],
                $row['round_number'],
                $row['table_number'],
                $row['bracket_round_label'],
                $row['bracket_slot'],
                $row['display_order'],
                $row['generation_number'],
                $row['deck_a_id'],
                $row['deck_b_id'],
                $row['deck_c_id'],
                $row['deck_d_id'],
                $row['deck_e_id'],
                $row['deck_f_id'],
                $row['scheduled_on'],
                $row['starts_at'],
                $row['notes'],
            ]);
            $created++;
        }
        return $created;
    }
}
