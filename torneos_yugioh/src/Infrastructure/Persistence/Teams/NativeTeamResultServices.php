<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamResultServices;

final readonly class NativeTeamResultServices implements TeamResultServices
{
    public function __construct(private PDO $pdo) {}

    public function competitionForMatch(int $matchId): array
    {
        return \ygo_competition_for_match($this->pdo, $matchId);
    }

    public function kind(array $competition): string
    {
        return \ygo_team_kind($competition);
    }

    public function requireOpen(int $seasonId): void
    {
        \ygo_season_require_open($this->pdo, $seasonId, true, true);
    }

    public function assertEditable(int $matchId): void
    {
        \ygo_admission_assert_result_editable($this->pdo, $matchId);
        \ygo_workflow_assert_result_editable($this->pdo, $matchId);
    }

    public function validateDate(int $matchId): array
    {
        return \ygo_validate_match_result_date($this->pdo, $matchId);
    }

    public function workflow(array $competition): ?array
    {
        return \ygo_workflow_load($this->pdo, $competition);
    }

    public function evaluate(int $matchId, array $sequence): array
    {
        return \ygo_evaluate_duel_with_rule($this->pdo, $matchId, $sequence);
    }

    public function finalize(int $matchId, string $now): void
    {
        \ygo_completion_set_status($this->pdo, $matchId, 'FINALIZADA', $now);
    }

    public function standings(int $seasonId, int $competitionId): void
    {
        \ygo_persist_competition_standings(
            $this->pdo,
            $seasonId,
            $competitionId,
            'save_team_result',
        );
    }

    public function sync(int $matchId): void
    {
        \ygo_sync_meta_history($this->pdo);
        \ygo_sync_elo_history($this->pdo);
        \ygo_sync_global_ranking_history($this->pdo);
        \ygo_sync_medal_unlocks($this->pdo, $matchId);
    }
}
