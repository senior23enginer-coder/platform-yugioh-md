<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

/**
 * calendar-scheduling: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_normalize_existing_decks_to_tcg_advanced(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\DivisionMaintenanceModule::normalize($pdo)->execute();
}

function ygo_rebuild_qualifier_calendar(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\LegacyQualifierCalendarModule::create($pdo)->execute($_POST);
}

function ygo_next_competition_match_date(DateTimeImmutable $start, string $pattern, int $index, int $matchesPerDay = 1): string
{
    return \StationX\Yugioh\Domain\Calendar\MatchDatePattern::date($start, $pattern, $index, $matchesPerDay);
}

function ygo_secure_shuffle(array $items): array
{
    $items = array_values($items);
    for ($i = count($items) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        if ($i !== $j) {
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }
    return $items;
}

function ygo_schedule_qualifier_duels(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $duelsPerDeck, int $matchesPerDay = 1, string $phase = 'qualifier'): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_qualifier_duels($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$duelsPerDeck,$matchesPerDay,$phase);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    return \StationX\Yugioh\Infrastructure\Composition\FixtureGenerationModule::qualifier($pdo)->execute($competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $duelsPerDeck, $matchesPerDay, $phase);
}

function ygo_schedule_cup_duels(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_cup_duels($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    return \StationX\Yugioh\Infrastructure\Composition\FixtureGenerationModule::cup($pdo)->execute($competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $matchesPerDay);
}

function ygo_schedule_competition_by_model(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_schedule_competition_by_model_serialized($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay));
}

function ygo_schedule_competition_by_model_serialized(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_competition_by_model($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    return \StationX\Yugioh\Infrastructure\Composition\FixtureGenerationModule::model($pdo)->execute($competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $matchesPerDay);
}

function ygo_apply_division_from_qualifier(PDO $pdo, int $divisionId): int
{
    return \StationX\Yugioh\Infrastructure\Composition\DivisionMaintenanceModule::qualifier($pdo)->execute($divisionId);
}

function ygo_expected_qualifier_match_count(int $selectedDeckCount, int $participantsPerSide, int $duelsPerDeck): int
{
    return \StationX\Yugioh\Domain\Calendar\ScheduleQuota::expectedQualifierMatchCount($selectedDeckCount, $participantsPerSide, $duelsPerDeck);
}
