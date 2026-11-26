<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionHonorWriter;

final readonly class PdoCompetitionHonorWriter implements CompetitionHonorWriter
{
    public function __construct(private PDO $pdo) {}

    public function workflowHonor(array $competition, int $championId, int $runnerId): void
    {
        \ygo_workflow_honor($this->pdo, $competition, $championId, $runnerId);
    }

    public function record(array $competition, int $competitionId, int $championId, int $championTeamId, int $runnerId, int $runnerTeamId): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_honors (season_id, competition_id, champion_deck_id, champion_team_id, runner_up_deck_id, runner_up_team_id, title, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                (int) $competition['season_id'],
                $competitionId,
                $championId,
                $championTeamId > 0 ? $championTeamId : null,
                $runnerId > 0 ? $runnerId : null,
                $runnerTeamId > 0 ? $runnerTeamId : null,
                'Campeon - ' . (string) $competition['name'],
                'Registrado automaticamente al cerrar el evento.',
            ]);
    }
}
