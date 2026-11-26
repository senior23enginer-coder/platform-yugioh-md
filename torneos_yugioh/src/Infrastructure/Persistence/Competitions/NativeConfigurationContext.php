<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionConfigurationContext;

final readonly class NativeConfigurationContext implements CompetitionConfigurationContext
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function auditSnapshot(int $competitionId): array
    {
        return \ygo_event_audit_snapshot($this->pdo, $competitionId);
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

    public function recordHistory(int $seasonId, int $competitionId, ?string $from, string $to, string $action, array $payload): void
    {
        \ygo_record_competition_status_history($this->pdo, $seasonId, $competitionId, $from, $to, $action, $payload);
    }

    public function gate(int $competitionId): array
    {
        return \ygo_competition_gate($this->pdo, $competitionId);
    }

    public function validGameRuleId(int $seasonId, int $postedRuleId, ?int $fallbackRuleId, string $competitionType, string $executionModel): ?int
    {
        return \ygo_valid_game_rule_id($this->pdo, $seasonId, $postedRuleId, $fallbackRuleId, $competitionType, $executionModel);
    }

    public function templateBaseCode(string $code): string
    {
        return \ygo_template_base_code($code);
    }

    public function workflowModels(): array
    {
        return \ygo_workflow_models();
    }

    public function expectedQualifierMatches(int $selectedDeckCount, int $participantsPerSide, int $duelsPerDeck): int
    {
        return \ygo_expected_qualifier_match_count($selectedDeckCount, $participantsPerSide, $duelsPerDeck);
    }
}
