<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionDefinitionServices;

final readonly class NativeCompetitionDefinitionServices implements CompetitionDefinitionServices
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function assertMutable(int $id): void
    {
        \ygo_assert_fixture_mutable($this->pdo, $id);
    }

    public function generateCode(int $seasonId, string $type, string $ruleset, string $name, int $id): string
    {
        return \ygo_generate_competition_code($this->pdo, $seasonId, $type, $ruleset, $name, $id);
    }

    public function templateType(string $template): string
    {
        return \ygo_event_template_type($template);
    }

    public function requireExecutionType(string $type, string $execution): void
    {
        \ygo_require_execution_type($type, $execution);
    }

    public function choiceErrors(array $input): array
    {
        return \ygo_event_choice_errors($input);
    }

    public function templates(): array
    {
        return \ygo_event_available_templates();
    }

    public function uploadLogo(): string
    {
        return \ygo_store_event_logo_upload();
    }

    public function validGalleryLogo(string $url, string $type): string
    {
        return \ygo_valid_event_gallery_logo($url, $type);
    }

    public function gallery(): array
    {
        return \ygo_event_logo_gallery();
    }

    public function validGameRule(int $seasonId, int $posted, ?int $fallback, string $type, string $execution): ?int
    {
        return \ygo_valid_game_rule_id($this->pdo, $seasonId, $posted, $fallback, $type, $execution);
    }

    public function parameterFields(string $execution): array
    {
        return \ygo_execution_parameter_fields($execution);
    }

    public function contractErrors(array $input, ?array $preset): array
    {
        return \ygo_event_contract_errors($input, $preset);
    }

    public function history(int $seasonId, int $id, ?string $from, string $to, string $action, array $payload): void
    {
        \ygo_record_competition_status_history($this->pdo, $seasonId, $id, $from, $to, $action, $payload);
    }

    public function recordLogo(string $entity, int $id, string $url, string $source): void
    {
        \ygo_record_logo_history($this->pdo, $entity, $id, $url, $source);
    }

    public function persistCalendar(int $seasonId, int $id): void
    {
        \ygo_persist_competition_calendar_event($this->pdo, $seasonId, $id);
    }
}
