<?php
declare(strict_types=1);

/**
 * league-structure: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_sync_format_categories(PDO $pdo, int $seasonId): void
{
    global $divisionDefinitions;
    \StationX\Yugioh\Infrastructure\Composition\LeagueStructureModule::categories($pdo)->execute($seasonId, $divisionDefinitions);
}

function ygo_ensure_goat_league_structure(PDO $pdo, int $seasonId): array
{
    return \StationX\Yugioh\Infrastructure\Composition\LeagueStructureModule::goat($pdo)->execute($seasonId);
}

function ygo_provisional_category_for_rank(int $rank): string
{
    global $divisionDefinitions;
    return \StationX\Yugioh\Domain\Competitions\DivisionRankPolicy::category($rank, $divisionDefinitions);
}

function ygo_compute_standings(PDO $pdo, string $phase = 'league'): array
{
    return \StationX\Yugioh\Infrastructure\Composition\LeagueStructureModule::standings($pdo)->execute($phase);
}
