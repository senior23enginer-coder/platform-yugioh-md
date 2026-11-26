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
    $competitionId = (int) ($competition['id'] ?? 0);
    $divisionId = !empty($competition['division_id']) ? (int) $competition['division_id'] : null;
    $deckIds = array_values(array_unique(array_filter(array_map('intval', $deckIds), static fn(int $id): bool => $id > 0)));
    if ($competitionId <= 0 || count($deckIds) < 2) {
        return 0;
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ? AND round_number = ?');
    $exists->execute([$competitionId, $phase, $round]);
    if ((int) $exists->fetchColumn() > 0) {
        return 0;
    }
    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_matches
            (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status, scheduled_on, starts_at, venue, notes)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "main", ?, 1, ?, ?, "PENDIENTE", ?, "19:00:00", "Station X Tulua", ?)
    ');
    $pairs = intdiv(count($deckIds), 2);
    $created = 0;
    $seasonId = ygo_current_season_id($pdo);
    $scheduledBase = (string) (($competition['starts_on'] ?? '') ?: (new DateTimeImmutable())->format('Y-m-d'));
    for ($i = 0; $i < $pairs; $i++) {
        $deckA = $deckIds[$i];
        $deckB = $deckIds[count($deckIds) - 1 - $i];
        $displayOrder = ($round * 100) + ($i + 1);
        $scheduledOn = (new DateTimeImmutable($scheduledBase))->modify('+' . max(0, $round - 1) . ' days')->format('Y-m-d');
        $insert->execute([
            $divisionId,
            $competitionId,
            (int) ($competition['game_rule_id'] ?? 0) ?: null,
            $phase,
            (string) (($competition['match_format'] ?? '') ?: '1v1'),
            (int) (($competition['best_of'] ?? 0) ?: 3),
            max(1, min(3, (int) (($competition['participants_per_side'] ?? 0) ?: 1))),
            $round,
            $i + 1,
            $label,
            $i + 1,
            $displayOrder,
            $deckA,
            $deckB,
            $scheduledOn,
            'Generado por avance automatico de ' . $phase . '.',
        ]);
        $created++;
    }
    $program=ygo_fixed_program($pdo);
    if($phase==='cup' && count($deckIds)%2===1) {
        $bye=(int)$deckIds[intdiv(count($deckIds),2)];
        ygo_save_setting($pdo,'cup_bye_'.$competitionId.'_'.$round,json_encode([$bye]));
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$competitionId,'cup_round_bye',json_encode(['round'=>$round,'deck_id'=>$bye])]);
    }
    ygo_admission_schedule_stage($pdo,$competition,(string)($competition['starts_on']??date('Y-m-d')),(string)($competition['schedule_pattern']??'consecutive'),max(1,(int)($competition['qualifier_matches_per_day']??1)),$phase,$round);
    ygo_schedule_fixed_cup_round($pdo,$competitionId,$phase,$round);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $phase);
    return $created;
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
    $divisionId = ygo_first_division_id($pdo);
    $rows = $pdo->prepare("
        SELECT m.division_id, m.phase, m.round_number, m.table_number, m.deck_a_id, m.deck_b_id, r.result
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.phase = ? AND m.status = 'FINALIZADA'
        ORDER BY m.division_id, m.round_number, m.table_number
    ");
    $rows->execute([$phase]);
    $winnersByDivision = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $winner = $row['result'] === 'A' ? (int) $row['deck_a_id'] : ($row['result'] === 'B' ? (int) $row['deck_b_id'] : 0);
        if ($winner > 0) {
            $winnersByDivision[(int) $row['division_id']][] = $winner;
        }
    }
    $created = 0;
    foreach ($winnersByDivision as $divId => $winners) {
        $nextRound = (int) $pdo->query("SELECT COALESCE(MAX(round_number), 0) + 1 FROM sx_ygo_matches WHERE division_id = $divId AND phase = " . $pdo->quote($phase))->fetchColumn();
        for ($i = 0; $i + 1 < count($winners); $i += 2) {
            $slot = intdiv($i, 2) + 1;
            $pdo->prepare("
                INSERT IGNORE INTO sx_ygo_matches (division_id, phase, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, 'PENDIENTE')
            ")->execute([$divId ?: $divisionId, $phase, $nextRound, $slot, 'Ronda ' . $nextRound, $slot, (($nextRound - 1) * 100) + $slot, $winners[$i], $winners[$i + 1]]);
            $created++;
        }
    }
    return $created;
}

function ygo_auto_schedule_qualifier(PDO $pdo): int
{
    $startDate = (string) ($_POST['qualifier_schedule_start'] ?? (new DateTimeImmutable())->format('Y-m-d'));
    $startTime = (string) ($_POST['qualifier_schedule_time'] ?? '19:00');
    $tablesPerDay = max(1, min(12, (int) ($_POST['qualifier_schedule_tables'] ?? 6)));
    $matchesPerDay = max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? 1)));
    $tablesPerDay = $matchesPerDay;
    $minutesPerRound = max(20, min(180, (int) ($_POST['qualifier_schedule_minutes'] ?? 45)));
    $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime) ?: new DateTimeImmutable();
    $competitionId = ygo_selected_qualifier_competition_id($pdo);

    $stmt = $pdo->prepare("
        SELECT id, round_number, table_number
        FROM sx_ygo_matches
        WHERE phase = 'qualifier'
          AND (? IS NULL OR competition_id = ? OR competition_id IS NULL)
          AND status <> 'FINALIZADA'
        ORDER BY round_number, table_number, id
    ");
    $stmt->execute([$competitionId, $competitionId]);
    $matches = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $updated = 0;
    foreach ($matches as $index => $match) {
        $dayOffset = intdiv($index, $tablesPerDay);
        $slot = $index % $tablesPerDay;
        $dateTime = $base->modify('+' . $dayOffset . ' days')->modify('+' . ($slot * $minutesPerRound) . ' minutes');
        $pdo->prepare('UPDATE sx_ygo_matches SET competition_id=COALESCE(competition_id, ?), scheduled_on=?, starts_at=?, venue=COALESCE(NULLIF(venue, ""), ?), notes=COALESCE(NULLIF(notes, ""), ?) WHERE id=?')
            ->execute([
                $competitionId,
                $dateTime->format('Y-m-d'),
                $dateTime->format('H:i:s'),
                'Station X Tulua',
                'Programado automaticamente desde Gestion.',
                (int) $match['id'],
            ]);
        $updated++;
    }
    if ($competitionId) {
        $pdo->prepare('UPDATE sx_ygo_competitions SET qualifier_matches_per_day = ? WHERE id = ?')
            ->execute([$matchesPerDay, $competitionId]);
    }
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['competition', $competitionId, 'auto_schedule_qualifier', json_encode(['updated' => $updated, 'start' => $startDate, 'time' => $startTime, 'matches_per_day' => $matchesPerDay], JSON_UNESCAPED_UNICODE)]);
    return $updated;
}
