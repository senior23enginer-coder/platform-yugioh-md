<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\ManualMatchStore;

final readonly class PdoManualMatchStore implements ManualMatchStore
{
    public function __construct(private PDO $pdo) {}

    public function firstDivision(): int
    {
        return \ygo_first_division_id($this->pdo);
    }

    public function event(int $id, bool $active): array
    {
        $stmt = $this->pdo->prepare(
            $active
                ? 'SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1'
                : 'SELECT * FROM sx_ygo_competitions WHERE id=?',
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function nextFriendlyTable(): int
    {
        return max(
            1,
            (int) $this->pdo
                ->query(
                    "SELECT COALESCE(MAX(table_number), 0) + 1 FROM sx_ygo_matches WHERE competition_id IS NULL AND phase = 'friendly'",
                )
                ->fetchColumn(),
        );
    }

    public function save(array $values): void
    {
        $this->pdo
            ->prepare(
                "
            INSERT INTO sx_ygo_matches (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, judge, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE competition_id=VALUES(competition_id), game_rule_id=VALUES(game_rule_id), deck_a_id=VALUES(deck_a_id), deck_b_id=VALUES(deck_b_id), deck_c_id=VALUES(deck_c_id), deck_d_id=VALUES(deck_d_id), deck_e_id=VALUES(deck_e_id), deck_f_id=VALUES(deck_f_id), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), bracket_round_label=VALUES(bracket_round_label), bracket_slot=VALUES(bracket_slot), bracket_side=VALUES(bracket_side), display_order=VALUES(display_order), scheduled_on=VALUES(scheduled_on), starts_at=VALUES(starts_at), venue=VALUES(venue), judge=VALUES(judge), notes=VALUES(notes)
        ",
            )
            ->execute($values);
    }

    public function audit(string $action, array $payload): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute(['match', null, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }
}
