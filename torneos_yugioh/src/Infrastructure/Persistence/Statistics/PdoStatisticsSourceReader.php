<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Statistics;

use PDO;
use StationX\Yugioh\Application\Statistics\StatisticsSourceReader;

final readonly class PdoStatisticsSourceReader implements StatisticsSourceReader
{
    public function __construct(private PDO $pdo) {}

    public function events(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_competitions ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fixedMedals(): array
    {
        if (!\ygo_table_exists($this->pdo, 'sx_ygo_statistics_medals')) {
            return [];
        }
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_statistics_medals')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function matches(): array
    {
        return $this->pdo
            ->query(
                "SELECT m.*,r.result,r.score,r.d1,r.d2,r.d3,r.d4,r.d5,r.duel_details,r.dice_winner,
            r.updated_at result_updated_at,c.type event_type,c.event_ruleset,c.ranking_impact,c.season_id source_season,
            da.team_id current_team_a,db.team_id current_team_b
            FROM sx_ygo_matches m LEFT JOIN sx_ygo_match_results r ON r.match_id=m.id
            LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id
            LEFT JOIN sx_ygo_decks da ON da.id=m.deck_a_id LEFT JOIN sx_ygo_decks db ON db.id=m.deck_b_id ORDER BY m.id",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function snapshots(): array
    {
        return $this->pdo
            ->query(
                'SELECT match_id,side,deck_id,team_id,ruleset_code,data_origin,snapshot_status FROM sx_ygo_match_snapshots ORDER BY match_id,duel_number',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function collective(): array
    {
        return $this->pdo
            ->query(
                "SELECT season_id,setting_key,setting_value FROM sx_ygo_settings WHERE setting_key LIKE 'event_team_result_%'",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function honors(): array
    {
        return $this->pdo
            ->query(
                'SELECT h.*,c.status event_status FROM sx_ygo_honors h LEFT JOIN sx_ygo_competitions c ON c.id=h.competition_id ORDER BY h.id',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function seasons(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_seasons ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function freeForAll(): array
    {
        if (!\ygo_table_exists($this->pdo, 'sx_ygo_ffa_matches')) {
            return [];
        }
        return $this->pdo
            ->query(
                'SELECT f.*,c.season_id,c.type event_type,c.event_ruleset FROM sx_ygo_ffa_matches f JOIN sx_ygo_competitions c ON c.id=f.competition_id ORDER BY f.id',
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
