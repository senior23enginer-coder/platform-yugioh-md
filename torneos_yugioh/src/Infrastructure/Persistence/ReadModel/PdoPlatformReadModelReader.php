<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\ReadModel\PlatformReadModelReader;

final readonly class PdoPlatformReadModelReader implements PlatformReadModelReader
{
    public function __construct(private PDO $pdo) {}

    public function divisions(int $seasonId): array
    {
        $divisionRows = $this->pdo->prepare('SELECT * FROM sx_ygo_divisions WHERE season_id=? AND active=1 ORDER BY tier_order,id');
        $divisionRows->execute([$seasonId]);
        return $divisionRows->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decks(): array
    {
        $deckRows = $this->pdo->query("
            SELECT d.*, dv.code AS division_code, t.name AS team_name
                 , t.logo_url AS team_logo, t.short_code AS team_code, t.primary_color AS team_primary_color, t.secondary_color AS team_secondary_color, t.symbol AS team_symbol
            FROM sx_ygo_decks d
            LEFT JOIN sx_ygo_divisions dv ON dv.id = d.division_id AND dv.active = 1
            LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
            WHERE d.active = 1
            ORDER BY COALESCE(dv.tier_order, 9999), d.seed_rank, d.name
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($deckRows as &$deck) {
            foreach (['team_logo', 'cover_url', 'logo_url'] as $field) {
                $deck[$field] = \ygo_media_src((string) ($deck[$field] ?: ''));
            }
        }
        unset($deck);
        return $deckRows;
    }

    public function matches(): array
    {
        return $this->pdo->query("
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
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function statusHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_competition_status_history')
            ? $this->pdo->query('SELECT * FROM sx_ygo_competition_status_history ORDER BY created_at DESC, id DESC LIMIT 120')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function standingSnapshots(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_standing_snapshots')
            ? $this->pdo->query('SELECT * FROM sx_ygo_standing_snapshots ORDER BY competition_id, snapshot_key, position')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function players(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_players')
            ? $this->pdo->query("SELECT p.*, t.name AS team_name FROM sx_ygo_players p LEFT JOIN sx_ygo_teams t ON t.id=p.team_id WHERE p.active=1 ORDER BY p.display_name")->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function playerDecks(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_player_decks')
            ? $this->pdo->query('SELECT player_id, deck_id FROM sx_ygo_player_decks WHERE active=1')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function matchSnapshots(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_match_snapshots')
            ? $this->pdo->query('SELECT * FROM sx_ygo_match_snapshots ORDER BY captured_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function matchupAnalyses(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_matchup_analyses')
            ? $this->pdo->query("SELECT a.*, d.name AS deck_name, o.name AS opponent_name FROM sx_ygo_matchup_analyses a JOIN sx_ygo_decks d ON d.id=a.deck_id JOIN sx_ygo_decks o ON o.id=a.opponent_deck_id WHERE a.active=1 ORDER BY a.updated_at DESC, a.id DESC")->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function matchupAnalysisHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_matchup_analysis_history')
            ? $this->pdo->query('SELECT * FROM sx_ygo_matchup_analysis_history ORDER BY created_at DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function deckAnalysisProfiles(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_deck_analysis_profiles')
            ? $this->pdo->query('SELECT * FROM sx_ygo_deck_analysis_profiles ORDER BY updated_at DESC, deck_id')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function deckAnalysisProfileHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_deck_analysis_profile_history')
            ? $this->pdo->query('SELECT * FROM sx_ygo_deck_analysis_profile_history ORDER BY created_at DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function medalUnlocks(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_medal_unlocks')
            ? $this->pdo->query('SELECT * FROM sx_ygo_medal_unlocks ORDER BY unlocked_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function checkins(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_checkins')
            ? $this->pdo->query('SELECT * FROM sx_ygo_checkins ORDER BY checked_in_at DESC, id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function metaHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_meta_history')
            ? $this->pdo->query('SELECT * FROM sx_ygo_meta_history ORDER BY snapshot_on DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function eloHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_elo_history')
            ? $this->pdo->query('SELECT e.*,d.name AS deck_name,o.name AS opponent_name FROM sx_ygo_elo_history e LEFT JOIN sx_ygo_decks d ON d.id=e.deck_id LEFT JOIN sx_ygo_decks o ON o.id=e.opponent_deck_id ORDER BY e.played_on DESC,e.match_id DESC,e.id DESC LIMIT 4000')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function medalProgress(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_medal_progress')
            ? $this->pdo->query('SELECT * FROM sx_ygo_medal_progress ORDER BY entity_type,entity_id,unlocked DESC,rarity,medal_name')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function rankingHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_global_ranking_history')
            ? $this->pdo->query('SELECT h.*,d.name AS deck_name,t.name AS team_name FROM sx_ygo_global_ranking_history h LEFT JOIN sx_ygo_decks d ON d.id=h.deck_id LEFT JOIN sx_ygo_teams t ON t.id=d.team_id ORDER BY h.snapshot_on DESC,h.season_id,h.position LIMIT 5000')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function eventSummaries(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_event_summaries')
            ? $this->pdo->query('SELECT s.*,c.name AS competition_name,d1.name AS champion_name,d2.name AS runner_up_name FROM sx_ygo_event_summaries s LEFT JOIN sx_ygo_competitions c ON c.id=s.competition_id LEFT JOIN sx_ygo_decks d1 ON d1.id=s.champion_deck_id LEFT JOIN sx_ygo_decks d2 ON d2.id=s.runner_up_deck_id ORDER BY s.finalized_on DESC,s.competition_id DESC')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function teams(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_teams WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function competitions(): array
    {
        return $this->pdo->query("
            SELECT c.*, d.code AS division_code, s.name AS season_name, s.status AS season_status, s.starts_on AS season_starts_on, s.ends_on AS season_ends_on
            FROM sx_ygo_competitions c
            JOIN sx_ygo_seasons s ON s.id=c.season_id
            LEFT JOIN sx_ygo_divisions d ON d.id = c.division_id
            WHERE c.active = 1
            ORDER BY COALESCE(c.starts_on, '2999-12-31'), c.type, c.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function matchTypes(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_match_types WHERE active = 1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function competitionDeckRows(): array
    {
        return $this->pdo->query('SELECT competition_id, deck_id FROM sx_ygo_competition_decks WHERE active = 1 ORDER BY competition_id, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cards(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_deck_cards ORDER BY deck_id, section, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function media(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_deck_media ORDER BY deck_id, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logoHistory(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_logo_history ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function honors(): array
    {
        return $this->pdo->query("
            SELECT h.*, c.name AS competition_name, dc.name AS champion_name, dr.name AS runner_name,
                   tc.name AS champion_team_name, tr.name AS runner_team_name
            FROM sx_ygo_honors h
            LEFT JOIN sx_ygo_competitions c ON c.id = h.competition_id
            LEFT JOIN sx_ygo_decks dc ON dc.id = h.champion_deck_id
            LEFT JOIN sx_ygo_decks dr ON dr.id = h.runner_up_deck_id
            LEFT JOIN sx_ygo_teams tc ON tc.id = h.champion_team_id
            LEFT JOIN sx_ygo_teams tr ON tr.id = h.runner_up_team_id
            ORDER BY h.created_at DESC, h.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function audit(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_audit_log ORDER BY created_at DESC, id DESC LIMIT 80')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function events(): array
    {
        return $this->pdo->query("
            SELECT e.*, c.name AS competition_name, c.type AS competition_type, c.status AS competition_status, d.code AS division_code
            FROM sx_ygo_events e
            LEFT JOIN sx_ygo_competitions c ON c.id = e.competition_id
            LEFT JOIN sx_ygo_divisions d ON d.id = e.division_id
            WHERE e.active = 1
            ORDER BY e.event_date, e.starts_at, e.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rulesets(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_rulesets WHERE active = 1 ORDER BY format_family, label')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rulesetVersions(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_ruleset_versions WHERE active = 1 ORDER BY sort_order, COALESCE(effective_on, "9999-12-31") DESC, version_label DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gameRules(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_game_rules ORDER BY active DESC, priority_order, name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gameRuleConditions(): array
    {
        return $this->pdo->query('SELECT * FROM sx_ygo_game_rule_conditions WHERE active = 1 ORDER BY game_rule_id, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function seasons(): array
    {
        return $this->pdo->query('SELECT s.*, (SELECT COUNT(*) FROM sx_ygo_competitions c WHERE c.season_id=s.id) event_count, (SELECT COUNT(*) FROM sx_ygo_divisions d WHERE d.season_id=s.id) division_count FROM sx_ygo_seasons s ORDER BY s.starts_on DESC,s.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
}
