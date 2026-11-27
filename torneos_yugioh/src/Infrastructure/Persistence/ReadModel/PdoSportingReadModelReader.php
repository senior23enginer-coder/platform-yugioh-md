<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\ReadModel\SportingReadModelReader;

final readonly class PdoSportingReadModelReader implements SportingReadModelReader
{
    public function __construct(private PDO $pdo) {}

    public function divisions(int $seasonId): array
    {
        $divisionRows = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_divisions WHERE season_id=? AND active=1 ORDER BY tier_order,id',
        );
        $divisionRows->execute([$seasonId]);
        return $divisionRows->fetchAll(PDO::FETCH_ASSOC);
    }

    public function matches(): array
    {
        return $this->pdo
            ->query(
                "
            SELECT m.*, CASE WHEN m.phase = 'qualifier' THEN 'CLASIFICATORIO' ELSE dv.code END AS division_code,
                   c.name AS competition_name, c.type AS competition_type, c.status AS competition_status, c.event_ruleset AS competition_ruleset, c.ruleset_version AS competition_ruleset_version,
                   da.name AS deck_a_name, db.name AS deck_b_name,
                   ta.name AS team_a_name, tb.name AS team_b_name,
                   ta.short_code AS team_a_code, tb.short_code AS team_b_code,
                   ta.logo_url AS team_a_logo, tb.logo_url AS team_b_logo,
                   dc.name AS deck_c_name, dd.name AS deck_d_name, de.name AS deck_e_name, df.name AS deck_f_name,
                   r.d1, r.d2, r.d3, r.d4, r.d5, r.result, r.score, r.validation_status, r.notes AS result_notes,
                   r.dice_roll, r.turn_choice, r.dice_winner, r.turn_chosen_by, r.turn_decision, r.starts_player, r.duel_details, r.updated_at AS result_updated_at
            FROM sx_ygo_matches m
            LEFT JOIN sx_ygo_divisions dv ON dv.id = m.division_id
            JOIN sx_ygo_decks da ON da.id = m.deck_a_id
            JOIN sx_ygo_decks db ON db.id = m.deck_b_id
            LEFT JOIN sx_ygo_teams ta ON ta.id = da.team_id
            LEFT JOIN sx_ygo_teams tb ON tb.id = db.team_id
            LEFT JOIN sx_ygo_decks dc ON dc.id = m.deck_c_id
            LEFT JOIN sx_ygo_decks dd ON dd.id = m.deck_d_id
            LEFT JOIN sx_ygo_decks de ON de.id = m.deck_e_id
            LEFT JOIN sx_ygo_decks df ON df.id = m.deck_f_id
            LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
            LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
            WHERE m.competition_id IS NULL OR c.id IS NOT NULL
            ORDER BY dv.tier_order, m.display_order, m.round_number, m.table_number
        ",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function competitions(): array
    {
        return $this->pdo
            ->query(
                "
            SELECT c.*, d.code AS division_code, s.name AS season_name, s.status AS season_status, s.starts_on AS season_starts_on, s.ends_on AS season_ends_on
            FROM sx_ygo_competitions c
            JOIN sx_ygo_seasons s ON s.id=c.season_id
            LEFT JOIN sx_ygo_divisions d ON d.id = c.division_id
            WHERE c.active = 1
            ORDER BY COALESCE(c.starts_on, '2999-12-31'), c.type, c.name
        ",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function competitionDeckRows(): array
    {
        return $this->pdo
            ->query(
                'SELECT competition_id, deck_id FROM sx_ygo_competition_decks WHERE active = 1 ORDER BY competition_id, sort_order, id',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function events(): array
    {
        return $this->pdo
            ->query(
                "
            SELECT e.*, c.name AS competition_name, c.type AS competition_type, c.status AS competition_status, d.code AS division_code
            FROM sx_ygo_events e
            LEFT JOIN sx_ygo_competitions c ON c.id = e.competition_id
            LEFT JOIN sx_ygo_divisions d ON d.id = e.division_id
            WHERE e.active = 1
            ORDER BY e.event_date, e.starts_at, e.id
        ",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function seasons(): array
    {
        return $this->pdo
            ->query(
                'SELECT s.*, (SELECT COUNT(*) FROM sx_ygo_competitions c WHERE c.season_id=s.id) event_count, (SELECT COUNT(*) FROM sx_ygo_divisions d WHERE d.season_id=s.id) division_count FROM sx_ygo_seasons s ORDER BY s.starts_on DESC,s.id DESC',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
