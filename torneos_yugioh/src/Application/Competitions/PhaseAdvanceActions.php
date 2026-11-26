<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface PhaseAdvanceActions
{
    public function admission(array $competition): ?array;

    public function requireAdmission(array $competition, array $ids): void;

    public function teamKind(array $competition): string;

    public function workflowModels(): array;

    public function workflow(array $competition): ?array;

    public function generateWorkflow(array $competition): int;

    public function requireOpen(int $seasonId): void;

    public function requireSupportedModel(array $competition): void;

    public function createRound(array $competition, string $phase, array $ids, int $round, string $label): int;

    public function recordHonor(int $competitionId): void;

    public function targetSize(int $count): int;

    public function audit(int $competitionId, string $action, array $payload): void;
}
