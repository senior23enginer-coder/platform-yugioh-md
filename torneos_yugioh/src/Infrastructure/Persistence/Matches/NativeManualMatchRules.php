<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\ManualMatchRules;

final readonly class NativeManualMatchRules implements ManualMatchRules
{
    public function __construct(private PDO $pdo) {}

    public function teamKind(array $event): string
    {
        return \ygo_team_kind($event);
    }

    public function gameRule(int $posted, string $phase): ?int
    {
        return \ygo_valid_match_game_rule_id($this->pdo, \ygo_current_season_id($this->pdo), $posted, $phase);
    }

    public function validateDecks(?int $competitionId, int $divisionId, array $decks, int $participants): array
    {
        return \ygo_match_deck_validation($this->pdo, $competitionId, $divisionId, $decks, $participants);
    }

    public function rulesetDecks(string $ruleset): array
    {
        return \ygo_deck_ids_for_ruleset($this->pdo, $ruleset);
    }

    public function validateSchedule(?string $date, ?string $time, string $venue): array
    {
        return \ygo_validate_match_schedule($this->pdo, 0, $date, $time, $venue);
    }

    public function workflowModels(): array
    {
        return \ygo_workflow_models();
    }

    public function requireSupported(array $event): void
    {
        \ygo_require_supported_model(\ygo_team_context($this->pdo, $event));
    }

    public function rebuild(int $id, string $phase): void
    {
        \ygo_rebuild_competition_calendar_events_from_matches($this->pdo, \ygo_current_season_id($this->pdo), $id, $phase);
    }
}
