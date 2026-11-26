<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\MatchResultEffects;

final readonly class NativeMatchResultEffects implements MatchResultEffects
{
    public function __construct(private PDO $pdo) {}

    public function setStatus(int $matchId, string $status, string $now): void
    {
        \ygo_completion_set_status($this->pdo, $matchId, $status, $now);
    }

    public function persistStandings(int $competitionId): void
    {
        \ygo_persist_competition_standings($this->pdo, \ygo_current_season_id($this->pdo), $competitionId, 'save_match_result');
    }

    public function syncStatistics(int $competitionId, int $matchId): void
    {
        \ygo_sync_meta_history($this->pdo);
        \ygo_sync_elo_history($this->pdo);
        \ygo_sync_global_ranking_history($this->pdo);
        \ygo_sync_event_summaries($this->pdo, $competitionId ?: null);
        \ygo_sync_medal_unlocks($this->pdo, $matchId);
    }

    public function liveMatch(int $matchId): array
    {
        return \ygo_live_duel_match($this->pdo, $matchId);
    }

    public function initialLiveState(array $match): array
    {
        return \ygo_live_duel_initial_state($match);
    }
}
