<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\ReadModel\HistoricalReadModelReader;

final readonly class PdoHistoricalReadModelReader implements HistoricalReadModelReader
{
    public function __construct(private PDO $pdo) {}

    public function statusHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_competition_status_history')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_competition_status_history ORDER BY created_at DESC, id DESC LIMIT 120',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function standingSnapshots(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_standing_snapshots')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_standing_snapshots ORDER BY competition_id, snapshot_key, position',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function matchSnapshots(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_match_snapshots')
            ? $this->pdo
                ->query('SELECT * FROM sx_ygo_match_snapshots ORDER BY captured_at DESC, id DESC')
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function medalUnlocks(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_medal_unlocks')
            ? $this->pdo
                ->query('SELECT * FROM sx_ygo_medal_unlocks ORDER BY unlocked_at DESC, id DESC')
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function checkins(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_checkins')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_checkins ORDER BY checked_in_at DESC, id DESC LIMIT 300',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function metaHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_meta_history')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_meta_history ORDER BY snapshot_on DESC, id DESC LIMIT 2000',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function eloHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_elo_history')
            ? $this->pdo
                ->query(
                    'SELECT e.*,d.name AS deck_name,o.name AS opponent_name FROM sx_ygo_elo_history e LEFT JOIN sx_ygo_decks d ON d.id=e.deck_id LEFT JOIN sx_ygo_decks o ON o.id=e.opponent_deck_id ORDER BY e.played_on DESC,e.match_id DESC,e.id DESC LIMIT 4000',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function medalProgress(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_medal_progress')
            ? $this->pdo
                ->query(
                    'SELECT * FROM sx_ygo_medal_progress ORDER BY entity_type,entity_id,unlocked DESC,rarity,medal_name',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function rankingHistory(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_global_ranking_history')
            ? $this->pdo
                ->query(
                    'SELECT h.*,d.name AS deck_name,t.name AS team_name FROM sx_ygo_global_ranking_history h LEFT JOIN sx_ygo_decks d ON d.id=h.deck_id LEFT JOIN sx_ygo_teams t ON t.id=d.team_id ORDER BY h.snapshot_on DESC,h.season_id,h.position LIMIT 5000',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function eventSummaries(): array
    {
        return \ygo_table_exists($this->pdo, 'sx_ygo_event_summaries')
            ? $this->pdo
                ->query(
                    'SELECT s.*,c.name AS competition_name,d1.name AS champion_name,d2.name AS runner_up_name FROM sx_ygo_event_summaries s LEFT JOIN sx_ygo_competitions c ON c.id=s.competition_id LEFT JOIN sx_ygo_decks d1 ON d1.id=s.champion_deck_id LEFT JOIN sx_ygo_decks d2 ON d2.id=s.runner_up_deck_id ORDER BY s.finalized_on DESC,s.competition_id DESC',
                )
                ->fetchAll(PDO::FETCH_ASSOC)
            : [];
    }

    public function honors(): array
    {
        return $this->pdo
            ->query(
                "
            SELECT h.*, c.name AS competition_name, dc.name AS champion_name, dr.name AS runner_name,
                   tc.name AS champion_team_name, tr.name AS runner_team_name
            FROM sx_ygo_honors h
            LEFT JOIN sx_ygo_competitions c ON c.id = h.competition_id
            LEFT JOIN sx_ygo_decks dc ON dc.id = h.champion_deck_id
            LEFT JOIN sx_ygo_decks dr ON dr.id = h.runner_up_deck_id
            LEFT JOIN sx_ygo_teams tc ON tc.id = h.champion_team_id
            LEFT JOIN sx_ygo_teams tr ON tr.id = h.runner_up_team_id
            ORDER BY h.created_at DESC, h.id DESC
        ",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function audit(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_audit_log ORDER BY created_at DESC, id DESC LIMIT 80')
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
