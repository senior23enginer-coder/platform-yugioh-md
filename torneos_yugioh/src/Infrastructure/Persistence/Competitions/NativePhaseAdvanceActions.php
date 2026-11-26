<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\PhaseAdvanceActions;

final readonly class NativePhaseAdvanceActions implements PhaseAdvanceActions
{
    public function __construct(private PDO $pdo) {}

    public function admission(array $competition): ?array
    {
        return \ygo_admission_load($this->pdo, $competition);
    }

    public function requireAdmission(array $competition, array $ids): void
    {
        \ygo_admission_require($this->pdo, $competition, $ids, false);
    }

    public function teamKind(array $competition): string
    {
        return \ygo_team_kind($competition);
    }

    public function workflowModels(): array
    {
        return \ygo_workflow_models();
    }

    public function workflow(array $competition): ?array
    {
        return \ygo_workflow_load($this->pdo, $competition);
    }

    public function generateWorkflow(array $competition): int
    {
        return \ygo_workflow_generate($this->pdo, $competition);
    }

    public function requireOpen(int $seasonId): void
    {
        \ygo_season_require_open($this->pdo, $seasonId);
    }

    public function requireSupportedModel(array $competition): void
    {
        \ygo_require_supported_model($competition);
    }

    public function createRound(
        array $competition,
        string $phase,
        array $ids,
        int $round,
        string $label,
    ): int {
        return \ygo_create_competition_knockout_round(
            $this->pdo,
            $competition,
            $phase,
            $ids,
            $round,
            $label,
        );
    }

    public function recordHonor(int $competitionId): void
    {
        \ygo_record_competition_honor($this->pdo, $competitionId);
    }

    public function targetSize(int $count): int
    {
        return \ygo_knockout_target_size($count);
    }

    public function audit(int $competitionId, string $action, array $payload): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                'competition',
                $competitionId,
                $action,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
