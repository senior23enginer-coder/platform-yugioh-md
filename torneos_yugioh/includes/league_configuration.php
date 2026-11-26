<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';

function ygo_format_deck_ids(PDO $pdo, string $ruleset): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoFormatRosterReader($pdo))->deckIds($ruleset);
}

function ygo_validate_format_roster(PDO $pdo, string $ruleset, array $ids): array
{
    return \StationX\Yugioh\Infrastructure\Composition\LeagueRosterModule::format($pdo)->execute($ruleset, $ids);
}

function ygo_league_divisions(PDO $pdo, int $competitionId): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoDivisionMovementReader($pdo))->divisions($competitionId);
}

function ygo_validate_division_movements(array $divisions, array $input): array
{
    return \StationX\Yugioh\Domain\Competitions\DivisionMovementPolicy::validateDivisionMovements(
        $divisions,
        $input,
    );
}

function ygo_save_division_movements(PDO $pdo, int $competitionId, array $input): void
{
    \StationX\Yugioh\Infrastructure\Composition\DivisionMovementModule::configure($pdo)->execute($competitionId, $input);
}

function ygo_apply_configured_league_movements(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\DivisionMovementModule::apply($pdo)->execute($competitionId);
}

function ygo_league_deck_assignments(PDO $pdo): array
{
    return (new \StationX\Yugioh\Infrastructure\Persistence\Leagues\PdoLeagueRosterReader($pdo))->assignments();
}

function ygo_available_league_deck_ids(PDO $pdo, int $competitionId, array $ids): array
{
    return \StationX\Yugioh\Infrastructure\Composition\LeagueRosterModule::roster($pdo)->available($competitionId, $ids);
}

function ygo_validate_league_roster(PDO $pdo, int $competitionId, array $ids): void
{
    \StationX\Yugioh\Infrastructure\Composition\LeagueRosterModule::roster($pdo)->validate($competitionId, $ids);
}

function ygo_league_round_robin_plan(
    PDO $pdo,
    int $competitionId,
    array $selectedDeckIds,
    int $participantsPerSide,
    bool $doubleRound,
): array {
    return \StationX\Yugioh\Infrastructure\Composition\LeagueRosterModule::schedule($pdo)->execute(
        $competitionId,
        $selectedDeckIds,
        $participantsPerSide,
        $doubleRound,
    );
}
