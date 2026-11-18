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
    $done = (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = 'deck' AND action = 'normalize_existing_decks_tcg_advanced'")->fetchColumn();
    if ($done > 0) {
        return 0;
    }
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare("SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND code = 'TCG_AVANZADO' AND active = 1 LIMIT 1");
    $stmt->execute([$seasonId]);
    $tcgDivisionId = (int) $stmt->fetchColumn();
    if ($tcgDivisionId <= 0) {
        return 0;
    }
    $update = $pdo->prepare('UPDATE sx_ygo_decks SET division_id = ? WHERE active = 1 AND (division_id IS NULL OR division_id <> ?)');
    $update->execute([$tcgDivisionId, $tcgDivisionId]);
    $updated = $update->rowCount();
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['deck', null, 'normalize_existing_decks_tcg_advanced', json_encode(['division_id' => $tcgDivisionId, 'updated' => $updated], JSON_UNESCAPED_UNICODE)]);
    return (int) $updated;
}

function ygo_rebuild_qualifier_calendar(PDO $pdo): int
{
    $today = (new DateTimeImmutable())->format('Y-m-d');
    $startDate = (string) ($_POST['qualifier_schedule_start'] ?? $today);
    $startTime = (string) ($_POST['qualifier_schedule_time'] ?? '19:00');
    $matchesPerDay = max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? 1)));
    $dayInterval = max(1, min(7, (int) ($_POST['qualifier_day_interval'] ?? 1)));
    $minutesPerMatch = max(20, min(180, (int) ($_POST['qualifier_schedule_minutes'] ?? 45)));
    $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime) ?: new DateTimeImmutable($today . ' 19:00');
    $seasonId = ygo_current_season_id($pdo);
    $divisionId = ygo_first_division_id($pdo);
    $competitionId = ygo_selected_qualifier_competition_id($pdo);
    if ($competitionId) {
        $pdo->prepare('UPDATE sx_ygo_competitions SET qualifier_matches_per_day = ? WHERE id = ?')
            ->execute([$matchesPerDay, $competitionId]);
    }
    $deckIds = array_map('intval', $pdo->query('SELECT id FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, id')->fetchAll(PDO::FETCH_COLUMN));
    if (!$divisionId || count($deckIds) < 2) {
        return 0;
    }

    $pdo->prepare("DELETE r FROM sx_ygo_match_results r INNER JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.phase = 'qualifier' AND m.status <> 'FINALIZADA' AND (? IS NULL OR m.competition_id = ? OR m.competition_id IS NULL)")->execute([$competitionId, $competitionId]);
    $pdo->prepare("DELETE FROM sx_ygo_matches WHERE phase = 'qualifier' AND status <> 'FINALIZADA' AND (? IS NULL OR competition_id = ? OR competition_id IS NULL)")->execute([$competitionId, $competitionId]);
    $pdo->prepare("DELETE FROM sx_ygo_events WHERE season_id=? AND event_type='qualifier' AND status <> 'completed' AND (? IS NULL OR competition_id = ?)")->execute([$seasonId, $competitionId, $competitionId]);

    $players = $deckIds;
    if (count($players) % 2 === 1) {
        $players[] = 0;
    }
    $opponents = [];
    $played = [];
    foreach ($deckIds as $deckId) {
        $opponents[$deckId] = [];
        $played[$deckId] = 0;
    }
    $pairs = [];
    $roundCount = 3;
    $n = count($players);
    for ($round = 1; $round <= $roundCount; $round++) {
        $table = 1;
        for ($i = 0; $i < $n / 2; $i++) {
            $a = (int) $players[$i];
            $b = (int) $players[$n - 1 - $i];
            if ($a <= 0 || $b <= 0 || $a === $b) {
                continue;
            }
            $key = min($a, $b) . '-' . max($a, $b);
            if (isset($pairs[$key])) {
                continue;
            }
            $pairs[$key] = [$round, $table++, $a, $b];
            $opponents[$a][$b] = true;
            $opponents[$b][$a] = true;
            $played[$a]++;
            $played[$b]++;
        }
        $fixed = array_shift($players);
        $last = array_pop($players);
        array_unshift($players, $fixed, $last);
    }

    $table = 1;
    while (count(array_filter($played, static fn(int $count): bool => $count < 3)) > 0) {
        $needs = array_keys(array_filter($played, static fn(int $count): bool => $count < 3));
        $a = (int) array_shift($needs);
        $b = 0;
        foreach (array_merge($needs, $deckIds) as $candidate) {
            $candidate = (int) $candidate;
            if ($candidate !== $a && empty($opponents[$a][$candidate])) {
                $b = $candidate;
                break;
            }
        }
        if ($b <= 0) {
            break;
        }
        $key = min($a, $b) . '-' . max($a, $b);
        $pairs[$key] = [4, $table++, $a, $b];
        $opponents[$a][$b] = true;
        $opponents[$b][$a] = true;
        $played[$a]++;
        $played[$b]++;
    }

    uasort($pairs, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    $total = count($pairs);
    $insert = $pdo->prepare("
        INSERT INTO sx_ygo_matches (division_id, competition_id, phase, round_number, table_number, deck_a_id, deck_b_id, status, scheduled_on, starts_at, venue, notes)
        VALUES (?, ?, 'qualifier', ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?)
    ");
    $created = 0;
    foreach (array_values($pairs) as $index => $pair) {
        $dayIndex = intdiv($index, $matchesPerDay);
        $slot = $index % $matchesPerDay;
        if ($total % $matchesPerDay === 1 && $index === $total - 1 && $dayIndex > 0) {
            $dayIndex--;
            $slot = $matchesPerDay;
        }
        $dateTime = $base->modify('+' . ($dayIndex * $dayInterval) . ' days')->modify('+' . ($slot * $minutesPerMatch) . ' minutes');
        $insert->execute([
            $divisionId,
            $competitionId,
            $pair[0],
            $pair[1],
            $pair[2],
            $pair[3],
            $dateTime->format('Y-m-d'),
            $dateTime->format('H:i:s'),
            'Station X Tulua',
            'Clasificatorio reprogramado desde hoy, con minimo 4 combates por jornada.',
        ]);
        $created++;
    }
    $eventStmt = $pdo->prepare("
        INSERT INTO sx_ygo_events (season_id, competition_id, division_id, code, title, event_type, status, event_date, starts_at, venue, notes)
        VALUES (?, ?, ?, ?, ?, 'qualifier', 'scheduled', ?, ?, 'Station X Tulua', ?)
    ");
    $days = [];
    foreach (array_values($pairs) as $index => $_pair) {
        $dayIndex = intdiv($index, $matchesPerDay);
        if ($total % $matchesPerDay === 1 && $index === $total - 1 && $dayIndex > 0) {
            $dayIndex--;
        }
        $day = $base->modify('+' . ($dayIndex * $dayInterval) . ' days')->format('Y-m-d');
        $days[$day] = ($days[$day] ?? 0) + 1;
    }
    $journey = 1;
    foreach ($days as $day => $count) {
        $eventStmt->execute([
            $seasonId,
            $competitionId,
            $divisionId,
            'clasificatorio-' . ($competitionId ?: 'general') . '-jornada-' . $journey,
            'Clasificatorio - Jornada ' . $journey,
            $day,
            $startTime . ':00',
            $count . ' combates programados. Registro habilitado desde Clasificatorio.',
        ]);
        $journey++;
    }
    $dayKeys = array_keys($days);
    $endDate = $dayKeys ? (string) end($dayKeys) : $base->modify('+' . ((intdiv(max(0, $total - 1), $matchesPerDay)) * $dayInterval) . ' days')->format('Y-m-d');
    if ($competitionId) {
        $pdo->prepare("UPDATE sx_ygo_competitions SET status='active', starts_on=?, ends_on=? WHERE id=?")
            ->execute([$startDate, $endDate, $competitionId]);
    }
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['competition', $competitionId, 'rebuild_qualifier_calendar', json_encode(['created' => $created, 'start' => $startDate, 'interval_days' => $dayInterval, 'matches_per_day' => $matchesPerDay], JSON_UNESCAPED_UNICODE)]);
    return $created;
}

function ygo_next_competition_match_date(DateTimeImmutable $start, string $pattern, int $index, int $matchesPerDay = 1): string
{
    $dayIndex = intdiv(max(0, $index), max(1, min(5, $matchesPerDay)));
    if ($pattern === 'weekend') {
        $date = $start;
        $weekendIndex = 0;
        while ((int) $date->format('N') < 6) {
            $date = $date->modify('+1 day');
        }
        while ($weekendIndex < $dayIndex) {
            $date = $date->modify('+1 day');
            if ((int) $date->format('N') >= 6) {
                $weekendIndex++;
            }
        }
        return $date->format('Y-m-d');
    }
    $interval = ['consecutive' => 1, 'every_other_day' => 2, 'every_2_days' => 3, 'every_3_days' => 4][$pattern] ?? 1;
    return $start->modify('+' . ($dayIndex * $interval) . ' days')->format('Y-m-d');
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

    if(!in_array((string)($competition['execution_model']??'manual'),['swiss','swiss_top_cut'],true)) throw new InvalidArgumentException('El programador suizo no puede ejecutar otro motor.');

    ygo_require_execution_type((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
    $fresh=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$fresh->execute([$competition['id']]);$competition=$fresh->fetch(PDO::FETCH_ASSOC) ?: $competition;
    $competition=ygo_team_context($pdo,$competition);ygo_require_supported_model($competition);
    ygo_assert_fixture_mutable($pdo, (int)($competition['id'] ?? 0));
    $selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds);
    return ygo_workflow_generate($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);
}

function ygo_schedule_cup_duels(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_cup_duels($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    if(!in_array((string)($competition['execution_model']??'manual'),['single_elimination','bracket','top_cut','playoffs'],true)) throw new InvalidArgumentException('El programador de llave no puede ejecutar una serie, modo manual u otro motor.');

    ygo_require_execution_type((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
    ygo_require_supported_model($competition);
    $fresh=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$fresh->execute([$competition['id']]);$competition=$fresh->fetch(PDO::FETCH_ASSOC) ?: $competition;
    ygo_assert_fixture_mutable($pdo, (int)($competition['id'] ?? 0));
    $selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds);

    $matchesPerDay = max(1, min(5, $matchesPerDay));
    $competitionId = (int) ($competition['id'] ?? 0);
    $seasonId = ygo_current_season_id($pdo);
    $divisionId = !empty($competition['division_id']) ? (int) $competition['division_id'] : null;
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $minimumDecks = $participantsPerSide * 2;
    $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
    if ($competitionId <= 0 || count($selectedDeckIds) < $minimumDecks) {
        return 0;
    }
    $generationStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = 'competition' AND entity_id = ? AND action = 'configure_competition' AND payload LIKE '%\"mode\":\"schedule_cup\"%'");
    $generationStmt->execute([$competitionId]);
    $generation = ((int) $generationStmt->fetchColumn()) + 1;
    $selectedDeckIds = ygo_admission_load($pdo,$competition)!==null ? $selectedDeckIds : ygo_secure_shuffle($selectedDeckIds);
    $groups = ygo_roster_groups_for_match($pdo, $selectedDeckIds, $participantsPerSide);
    $groupCount = count($groups);
    if ($groupCount < 2) {
        return 0;
    }

    $pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.competition_id = ? AND m.status <> "FINALIZADA"')->execute([$competitionId]);
    $pdo->prepare('DELETE FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"')->execute([$competitionId]);

    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_matches
            (division_id, competition_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, notes)
        VALUES
            (?, ?, "cup", ?, ?, ?, 1, ?, "Octavos / ronda inicial", ?, "main", ?, ?, ?, ?, ?, ?, ?, ?, "PENDIENTE", ?, ?, "Station X Tulua", ?)
    ');
    $start = new DateTimeImmutable($startDate);
    $pairs = intdiv($groupCount, 2);
    $created = 0;
    for ($i = 0; $i < $pairs; $i++) {
        $leftIndex = $i;
        $rightIndex = $groupCount - 1 - $i;
        if (($generation + $i) % 2 === 0) {
            [$leftIndex, $rightIndex] = [$rightIndex, $leftIndex];
        }
        $sideA = array_pad($groups[$leftIndex], 3, null);
        $sideB = array_pad($groups[$rightIndex], 3, null);
        $scheduledOn = ygo_next_competition_match_date($start, $pattern, $created, $matchesPerDay);
        $slot = $created % $matchesPerDay;
        $startsAt = (new DateTimeImmutable('19:00'))->modify('+' . ($slot * 45) . ' minutes')->format('H:i:s');
        $insert->execute([
            $divisionId,
            $competitionId,
            $matchFormat,
            (int) ($competition['best_of'] ?? 3),
            $participantsPerSide,
            $created + 1,
            $created + 1,
            $created + 1,
            $generation,
            $sideA[0],
            $sideB[0],
            $sideA[1],
            $sideB[1],
            $sideA[2],
            $sideB[2],
            $scheduledOn,
            $startsAt,
            'Generado por algoritmo de copa. Generacion ' . $generation . '. Duelos por dia: ' . $matchesPerDay . '.',
        ]);
        $created++;
    }
    if($groupCount%2===1) ygo_save_setting($pdo,'cup_bye_'.$competitionId.'_1',json_encode([(int)$groups[intdiv($groupCount,2)][0]]));
    ygo_admission_schedule_stage($pdo,$competition,$startDate,$pattern,$matchesPerDay);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, 'cup');
    return $created;
}

function ygo_schedule_competition_by_model(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_schedule_competition_by_model_serialized($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay));
}

function ygo_schedule_competition_by_model_serialized(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_competition_by_model($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    ygo_require_execution_type((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
    $fresh=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$fresh->execute([$competition['id']]);$competition=$fresh->fetch(PDO::FETCH_ASSOC) ?: $competition;
    $competition=ygo_team_context($pdo,$competition);ygo_require_supported_model($competition);
    ygo_assert_fixture_mutable($pdo, (int)($competition['id'] ?? 0));
    $selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds);
    if(ygo_team_kind($competition)!=='' || in_array((string)$competition['execution_model'],ygo_workflow_models(),true))return ygo_workflow_generate($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);

    $competitionId = (int) ($competition['id'] ?? 0);
    $seasonId = ygo_current_season_id($pdo);
    $divisionId = (int) (($competition['division_id'] ?? 0) ?: ygo_first_division_id($pdo));
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $matchesPerDay = max(1, min(5, $matchesPerDay));
    $selectedDeckIds = ygo_admission_load($pdo,$competition)!==null ? $selectedDeckIds : ygo_secure_shuffle($selectedDeckIds);
    $groups = ygo_roster_groups_for_match($pdo, $selectedDeckIds, $participantsPerSide);
    if ($competitionId <= 0 || $divisionId <= 0 || count($groups) < 2) {
        return 0;
    }
    $type = (string) (($competition['type'] ?? '') ?: 'tournament');
    $execution = (string) (($competition['execution_model'] ?? '') ?: 'manual');
    $phaseStructure = 'NONE'; // Engine decides scheduling; a descriptive phase must not select another algorithm.
    if(in_array($execution,ygo_workflow_models(),true))return ygo_workflow_generate($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);
    if($execution==='free_for_all')throw new InvalidArgumentException('Crea las mesas de3 o4 jugadores desde Battle Royale en la ficha.');
    if ($execution==='manual') throw new InvalidArgumentException('Modo manual: crea sus combates expresamente; no se generara un round robin.');
    $phase = in_array($type, ['league'], true) ? 'league' : ($type === 'world' ? 'world' : ($type === 'final' ? 'final' : 'tournament'));
    if (in_array($execution, ['single_elimination', 'double_elimination', 'top_cut', 'playoffs', 'bracket', 'series'], true)) {
        return ygo_schedule_cup_duels($pdo, $competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $matchesPerDay);
    }
    if (in_array($execution, ['swiss', 'swiss_top_cut'], true) || $type === 'qualifier') {
        $duels = max(2, min(5, count($groups) - 1));
        $swissPhase = $type === 'qualifier' ? 'qualifier' : 'swiss';
        return ygo_schedule_qualifier_duels($pdo, $competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $duels, $matchesPerDay, $swissPhase);
    }

    $leaguePlan=null;
    if ($type==='league') {
        $finished=$pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=? AND status='FINALIZADA'");
        $finished->execute([$competitionId]);
        if((int)$finished->fetchColumn()>0) throw new InvalidArgumentException('No se puede regenerar una liga que ya tiene combates finalizados.');
        $leaguePlan=ygo_league_round_robin_plan($pdo,$competitionId,$selectedDeckIds,$participantsPerSide,$execution==='double_round_robin');
    }

    $pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.competition_id = ? AND m.status <> "FINALIZADA"')->execute([$competitionId]);
    $pdo->prepare('DELETE FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"')->execute([$competitionId]);

    if ($leaguePlan!==null) {
        $groups=$leaguePlan['groups'];
        $rounds=$leaguePlan['rounds'];
    } elseif (in_array($execution, ['groups', 'groups_knockout'], true) || str_contains($phaseStructure, 'GROUPS')) {
        $rounds = [];
        $chunks = array_chunk(range(0, count($groups) - 1), max(2, (int) ceil(count($groups) / 2)));
        foreach ($chunks as $chunk) {
            $localGroups = array_map(static fn(int $index): array => $groups[$index], $chunk);
            $localRounds = ygo_round_robin_pairs($localGroups, false);
            foreach ($localRounds as $roundPairs) {
                $rounds[] = array_map(static function (array $pair) use ($chunk): array {
                    return [$chunk[$pair[0]], $chunk[$pair[1]]];
                }, $roundPairs);
            }
        }
        $phase = 'groups';
    } else {
        $rounds = ygo_round_robin_pairs($groups, $execution === 'double_round_robin');
    }

    $generationStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = "competition" AND entity_id = ? AND action = "configure_competition"');
    $generationStmt->execute([$competitionId]);
    $generation = ((int) $generationStmt->fetchColumn()) + 1;
    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_matches
            (division_id, competition_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, notes)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "main", ?, ?, ?, ?, ?, ?, ?, ?, "PENDIENTE", ?, ?, "Station X Tulua", ?)
    ');
    $start = new DateTimeImmutable($startDate);
    $created = 0;
    foreach ($rounds as $roundIndex => $pairs) {
        foreach ($pairs as $tableIndex => $pair) {
            $sideA = array_pad($groups[(int) $pair[0]], 3, null);
            $sideB = array_pad($groups[(int) $pair[1]], 3, null);
            $scheduledOn = ygo_next_competition_match_date($start, $pattern, $created, $matchesPerDay);
            $slot = $created % $matchesPerDay;
            $startsAt = (new DateTimeImmutable('19:00'))->modify('+' . ($slot * 45) . ' minutes')->format('H:i:s');
            $insert->execute([
                (int) ($pair[2] ?? $divisionId),
                $competitionId,
                $phase,
                $matchFormat,
                (int) ($competition['best_of'] ?? 3),
                $participantsPerSide,
                $roundIndex + 1,
                (int) ($pair[3] ?? ($tableIndex + 1)),
                'Ronda ' . ($roundIndex + 1),
                (int) ($pair[3] ?? ($tableIndex + 1)),
                $created + 1,
                $generation,
                $sideA[0],
                $sideB[0],
                $sideA[1],
                $sideB[1],
                $sideA[2],
                $sideB[2],
                $scheduledOn,
                $startsAt,
                'Generado por modelo ' . $execution . '. Generacion ' . $generation . '.',
            ]);
            $created++;
        }
    }
    ygo_admission_schedule_stage($pdo,$competition,$startDate,$pattern,$matchesPerDay);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $phase);
    return $created;
}

function ygo_apply_division_from_qualifier(PDO $pdo, int $divisionId): int
{
    if ($divisionId <= 0) {
        return 0;
    }
    $divisionStmt = $pdo->prepare('SELECT * FROM sx_ygo_divisions WHERE id = ? AND active = 1 LIMIT 1');
    $divisionStmt->execute([$divisionId]);
    $division = $divisionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $qualifierId = (int) ($division['source_qualifier_id'] ?? 0);
    $rankFrom = (int) ($division['rank_from'] ?? 0);
    $rankTo = (int) ($division['rank_to'] ?? 0);
    if ($qualifierId <= 0 || $rankFrom <= 0 || $rankTo <= 0) {
        return 0;
    }
    if ($rankTo < $rankFrom) {
        [$rankFrom, $rankTo] = [$rankTo, $rankFrom];
    }
    $competitionStmt = $pdo->prepare("SELECT status FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1");
    $competitionStmt->execute([$qualifierId]);
    $status = ygo_normalize_status_key((string) ($competitionStmt->fetchColumn() ?: ''));
    if (!in_array($status, ['completed', 'finalizado', 'finalizada'], true)) {
        return 0;
    }
    $ranking = ygo_competition_standings($pdo, $qualifierId);
    $slice = array_slice($ranking, $rankFrom - 1, ($rankTo - $rankFrom) + 1);
    if (!$slice) {
        return 0;
    }
    $update = $pdo->prepare('UPDATE sx_ygo_decks SET division_id = ?, seed_rank = ? WHERE id = ?');
    $count = 0;
    foreach ($slice as $index => $row) {
        $deckId = (int) ($row['deck_id'] ?? $row['id'] ?? 0);
        if ($deckId <= 0) {
            continue;
        }
        $update->execute([$divisionId, $index + 1, $deckId]);
        $count++;
    }
    $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count = ? WHERE id = ?')->execute([$count, $divisionId]);
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['division', $divisionId, 'apply_from_qualifier', json_encode(['qualifier_id' => $qualifierId, 'rank_from' => $rankFrom, 'rank_to' => $rankTo, 'moved' => $count], JSON_UNESCAPED_UNICODE)]);
    return $count;
}

function ygo_expected_qualifier_match_count(int $selectedDeckCount, int $participantsPerSide, int $duelsPerDeck): int
{
    return \StationX\Yugioh\Domain\Calendar\ScheduleQuota::expectedQualifierMatchCount($selectedDeckCount, $participantsPerSide, $duelsPerDeck);
}
