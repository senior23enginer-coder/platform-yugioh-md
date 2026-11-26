<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\ReadModel;

use PDO;
use StationX\Yugioh\Application\Bootstrap\SeasonMaintenancePort;

final readonly class NativeSeasonMaintenance implements SeasonMaintenancePort
{
    public function __construct(private PDO $pdo) {}

    public function ensureSettings(): void
    {
        \ygo_ensure_default_settings($this->pdo);
    }

    public function ensureLabels(): void
    {
        \ygo_ensure_default_ui_labels($this->pdo);
    }

    public function removeSeededDivisions(int $seasonId, array $definitions): int
    {
        return \ygo_remove_seeded_divisions($this->pdo, $seasonId, $definitions);
    }

    public function ensureRulesets(int $seasonId, array $options): void
    {
        \ygo_ensure_default_rulesets($this->pdo, $seasonId, $options);
    }

    public function ensureRulesetVersions(int $seasonId): void
    {
        \ygo_ensure_default_ruleset_versions($this->pdo, $seasonId);
    }

    public function ensureMatchTypes(): void
    {
        \ygo_ensure_default_match_types($this->pdo);
    }

    public function ensureGameRules(int $seasonId): void
    {
        \ygo_ensure_default_game_rules($this->pdo, $seasonId);
    }

    public function ensureGoatStructure(int $seasonId): array
    {
        return \ygo_ensure_goat_league_structure($this->pdo, $seasonId);
    }

    public function applyHistoricalSchedule(): array
    {
        return \ygo_apply_october_2026_schedule($this->pdo);
    }

    public function activateDueCompetitions(): int
    {
        return \ygo_activate_due_scheduled_competitions($this->pdo);
    }

    public function ensureCompetitionCodes(int $seasonId): void
    {
        \ygo_ensure_sequential_competition_codes($this->pdo, $seasonId);
    }

    public function syncDeckTeams(): int
    {
        return \ygo_sync_deck_teams($this->pdo);
    }

    public function normalizeDeckFormats(): int
    {
        return \ygo_normalize_existing_decks_to_tcg_advanced($this->pdo);
    }

    public function seedDeckAnalysis(): int
    {
        return \ygo_seed_deck_analysis_profiles($this->pdo);
    }
}
