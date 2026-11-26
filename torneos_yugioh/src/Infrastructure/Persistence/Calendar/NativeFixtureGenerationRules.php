<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\FixtureGenerationRules;

final readonly class NativeFixtureGenerationRules implements FixtureGenerationRules
{
    public function __construct(private PDO $pdo) {}

    public function requireExecutionType(string $type, string $execution): void
    {
        \ygo_require_execution_type($type, $execution);
    }

    public function requireSupportedModel(array $competition): void
    {
        \ygo_require_supported_model($competition);
    }

    public function teamContext(array $competition): array
    {
        return \ygo_team_context($this->pdo, $competition);
    }

    public function assertFixtureMutable(int $competitionId): void
    {
        \ygo_assert_fixture_mutable($this->pdo, $competitionId);
    }

    public function admit(array $competition, array $ids): array
    {
        return \ygo_admission_require($this->pdo, $competition, $ids);
    }

    public function admissionLoad(array $competition): ?array
    {
        return \ygo_admission_load($this->pdo, $competition);
    }

    public function teamKind(array $competition): string
    {
        return \ygo_team_kind($competition);
    }

    public function workflowModels(): array
    {
        return \ygo_workflow_models();
    }

    public function shuffle(array $ids): array
    {
        return \ygo_secure_shuffle($ids);
    }

    public function rosterGroups(array $ids, int $participantsPerSide): array
    {
        return \ygo_roster_groups_for_match($this->pdo, $ids, $participantsPerSide);
    }

    public function leaguePlan(
        int $competitionId,
        array $ids,
        int $participantsPerSide,
        bool $doubleRound,
    ): array {
        return \ygo_league_round_robin_plan(
            $this->pdo,
            $competitionId,
            $ids,
            $participantsPerSide,
            $doubleRound,
        );
    }
}
