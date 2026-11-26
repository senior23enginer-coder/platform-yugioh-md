<?php
declare(strict_types=1);

/**
 * event-dependencies: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_validate_match_result_date(PDO $pdo, int $matchId): array
{
    return \StationX\Yugioh\Infrastructure\Composition\MatchScheduleValidationModule::result($pdo)->execute($matchId);
}

function ygo_validate_match_schedule(PDO $pdo, int $matchId, ?string $scheduledOn, ?string $startsAt, ?string $venue = null, array $participantIds = []): array
{
    $manualDeckIds = [];
    if ($matchId === 0 && !$participantIds) {
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $side) {
            $manualDeckIds[] = (int) ($_POST['manual_deck_' . $side . '_id'] ?? 0);
        }
    }
    return \StationX\Yugioh\Infrastructure\Composition\MatchScheduleValidationModule::schedule($pdo)->execute(
        $matchId,
        $scheduledOn,
        $startsAt,
        $venue,
        $participantIds,
        $manualDeckIds,
    );
}

function ygo_competition_dependencies(PDO $pdo): array
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->dependencies();
}

function ygo_competition_result_count(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->resultCount($competitionId);
}

function ygo_competition_honor_count(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->honorCount($competitionId);
}

function ygo_competition_division_count(PDO $pdo, int $competitionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::reader($pdo)->divisionCount($competitionId);
}

function ygo_competition_dependency_satisfied(PDO $pdo, array $dependency, bool $completed, bool $hasResults, int $resultCount): bool
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::dependency($pdo)->execute(
        $dependency,
        $completed,
        $hasResults,
    );
}

function ygo_competition_gate(PDO $pdo, int $competitionId, bool $refresh=false): array
{
    static $cache = [];
    if ($refresh) {
        unset($cache[$competitionId]);
    }
    if (isset($cache[$competitionId])) {
        return $cache[$competitionId];
    }
    return $cache[$competitionId] = \StationX\Yugioh\Infrastructure\Composition\CompetitionGateModule::gate($pdo)->execute($competitionId);
}
