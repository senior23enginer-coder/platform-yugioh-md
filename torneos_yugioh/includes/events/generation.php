<?php
declare(strict_types=1);

/**
 * event-generation: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_regenerate_league_matches(PDO $pdo, int $seasonId): void
{
    \StationX\Yugioh\Infrastructure\Composition\LegacyEventFixtureModule::create($pdo)->regenerateLeagues($seasonId);
}

function ygo_qualifier_finalize_check(PDO $pdo, ?int $competitionId = null): array
{
    return \StationX\Yugioh\Infrastructure\Composition\QualifierFinalizationModule::create($pdo)->check($competitionId, (int) ($_POST['qualifier_competition_id'] ?? 0));
}

function ygo_finalize_qualifier(PDO $pdo, ?int $competitionId = null): bool
{
    return \StationX\Yugioh\Infrastructure\Composition\QualifierFinalizationModule::create($pdo)->execute($competitionId, (int) ($_POST['qualifier_competition_id'] ?? 0));
}

function ygo_first_division_id(PDO $pdo): int
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoLegacyEventFixtureReader($pdo))->firstDivisionId();
}

function ygo_decks_for_division(PDO $pdo, ?int $divisionId = null): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoLegacyEventFixtureReader($pdo))->decks($divisionId);
}

function ygo_clear_phase(PDO $pdo, int $divisionId, string $phase): void
{
    (new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoLegacyEventFixtureWriter($pdo))->clearPhase($divisionId, $phase);
}

function ygo_generate_knockout(PDO $pdo, int $divisionId, string $phase, array $decks): int
{
    return \StationX\Yugioh\Infrastructure\Composition\LegacyEventFixtureModule::create($pdo)->knockout($divisionId, $phase, $decks);
}

function ygo_generate_friendlies(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\LegacyEventFixtureModule::create($pdo)->friendlies();
}

function ygo_generate_cups(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\LegacyEventFixtureModule::create($pdo)->cups();
}

function ygo_generate_world(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\LegacyEventFixtureModule::create($pdo)->world();
}

function ygo_generate_leagues(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\LegacyEventFixtureModule::create($pdo)->leagues();
}

function ygo_selected_qualifier_competition_id(PDO $pdo): ?int
{
    return \StationX\Yugioh\Infrastructure\Composition\QualifierFinalizationModule::create($pdo)->select((int) ($_POST['qualifier_competition_id'] ?? 0));
}

function ygo_competition_phase_match_count(PDO $pdo, int $competitionId, string $phase): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionPhaseModule::queries($pdo)->count($competitionId, $phase);
}

function ygo_competition_phase_finished(PDO $pdo, int $competitionId, string $phase): bool
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionPhaseModule::queries($pdo)->finished($competitionId, $phase);
}

function ygo_competition_round_winners(PDO $pdo, int $competitionId, string $phase, int $round): array
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionPhaseModule::queries($pdo)->winners($competitionId, $phase, $round);
}

function ygo_deck_ids_for_ruleset(PDO $pdo, string $ruleset): array
{
    return ygo_format_deck_ids($pdo, $ruleset);
}

function ygo_create_competition_knockout_round(PDO $pdo, array $competition, string $phase, array $deckIds, int $round, string $label): int
{
    return \StationX\Yugioh\Infrastructure\Composition\PhaseMaintenanceModule::knockout($pdo)->execute($competition, $phase, $deckIds, $round, $label);
}

function ygo_record_competition_honor(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionHonorModule::create($pdo)->execute($competitionId);
}

function ygo_apply_league_division_movements(PDO $pdo, int $competitionId): int
{
    return ygo_apply_configured_league_movements($pdo, $competitionId);
}

function ygo_advance_competition_phase(PDO $pdo, int $competitionId): int
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_advance_competition_phase_serialized($pdo,$competitionId));
}

function ygo_advance_competition_phase_serialized(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionPhaseModule::advance($pdo)->execute($competitionId);
}

function ygo_advance_competition_phase_locked(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionPhaseModule::advance($pdo)->locked($competitionId);
}

function ygo_advance_phase(PDO $pdo, string $phase, ?int $competitionId = null): int
{
    if ($competitionId !== null && $competitionId > 0) {
        return ygo_advance_competition_phase($pdo, $competitionId);
    }
    return \StationX\Yugioh\Infrastructure\Composition\PhaseMaintenanceModule::legacy($pdo)->execute($phase);
}

function ygo_auto_schedule_qualifier(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\PhaseMaintenanceModule::qualifier($pdo)->execute($_POST);
}
