<?php
declare(strict_types=1);

/**
 * event-configuration: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_configure_competition(PDO $pdo): int
{
    $competitionId = (int) ($_POST['competition_id'] ?? 0);
    if ($competitionId <= 0) {
        return 0;
    }
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$competitionId, $seasonId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition) {
        return 0;
    }

    $auditBefore = ygo_event_audit_snapshot($pdo, $competitionId);
    $mode = (string) ($_POST['config_mode'] ?? 'save');
    if(in_array($mode,['start','schedule_qualifier','schedule_cup','save_schedule'],true)){
        $existing=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');$existing->execute([$competitionId]);
        if((int)$existing->fetchColumn()===0) ygo_require_supported_model(ygo_team_context($pdo,$competition));
        elseif(in_array($mode,['schedule_qualifier','schedule_cup','save_schedule'],true)) ygo_assert_fixture_mutable($pdo,$competitionId);
    }
    if (!in_array($mode, ['save', 'save_schedule', 'start', 'schedule_qualifier', 'schedule_cup'], true)) {
        $mode = 'save';
    }
    $competitionName = trim((string) ($_POST['competition_name'] ?? ($competition['name'] ?? '')));
    $competitionType = trim((string) ($_POST['competition_type'] ?? ($competition['type'] ?? 'special')));
    if (!empty($competition['type'])) {
        $competitionType = (string) $competition['type'];
    }
    if (!array_key_exists($competitionType, $GLOBALS['competitionTypeOptions'] ?? [])) {
        $competitionType = (string) ($competition['type'] ?? 'special');
    }
    $divisionId = !empty($competition['division_id']) ? (int)$competition['division_id'] : null;
    $ownedDivisions = ygo_league_divisions($pdo, $competitionId);
    $postedDivisionIds = array_map(static fn($division)=>(int)$division['id'], $ownedDivisions);
    if ($competitionType === 'league') {
        if (!$postedDivisionIds) throw new InvalidArgumentException('Asocia al menos una division a esta liga desde Divisiones.');
        if (!in_array($divisionId, $postedDivisionIds, true)) $divisionId = $postedDivisionIds[0];
    }
    $deckPoolMode = (string) ($_POST['competition_deck_pool'] ?? ($competition['deck_pool'] ?? 'all_active'));
    if (!in_array($deckPoolMode, ['all_active', 'division'], true)) {
        $deckPoolMode = 'all_active';
    }
    $leaguePromotionSlots = max(0, (int) ($_POST['competition_promotion_slots'] ?? ($competition['promotion_slots'] ?? 0)));
    $leagueRelegationSlots = max(0, (int) ($_POST['competition_relegation_slots'] ?? ($competition['relegation_slots'] ?? 0)));
    $leaguePromotionTo = ($_POST['competition_promotion_to'] ?? '') === '' ? null : (int) $_POST['competition_promotion_to'];
    $leagueRelegationTo = ($_POST['competition_relegation_to'] ?? '') === '' ? null : (int) $_POST['competition_relegation_to'];
    $lockDivisionMoves = isset($_POST['competition_lock_division_moves']) ? 1 : 0;
    if ($competitionType !== 'league' || count($postedDivisionIds) < 2) {
        $leaguePromotionSlots = 0;
        $leagueRelegationSlots = 0;
        $leaguePromotionTo = null;
        $leagueRelegationTo = null;
        $lockDivisionMoves = 0;
    }
    $eventRuleset = trim((string) ($_POST['competition_event_ruleset'] ?? ($competition['event_ruleset'] ?? 'custom')));
    $rulesetCheck = $pdo->prepare('SELECT code FROM sx_ygo_rulesets WHERE season_id=? AND code=? AND active=1 LIMIT 1');
    $rulesetCheck->execute([$seasonId, $eventRuleset]);
    if (!$rulesetCheck->fetchColumn()) {
        $eventRuleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
    }
    $rulesetVersion = trim((string) ($_POST['competition_ruleset_version'] ?? ($competition['ruleset_version'] ?? '')));
    $accent = trim((string) ($_POST['competition_accent'] ?? ($competition['accent'] ?? '#36d8ff')));
    $rules = trim((string) ($_POST['competition_rules'] ?? ($competition['rules'] ?? '')));
    $rawPostedStatus = trim((string) ($_POST['competition_status'] ?? ($competition['status'] ?? 'pending_configuration')));
    $currentStatusKey = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
    $postedStatus = in_array($rawPostedStatus, ['cancelled', 'resume'], true)
        ? $rawPostedStatus
        : (string) (($competition['status'] ?? '') ?: 'pending_configuration');
    if (in_array($currentStatusKey, ['cancelled', 'canceled', 'anulado', 'anulada'], true)) {
        $nextStatus = in_array($postedStatus, ['resume', 'pending_configuration'], true) ? 'pending_configuration' : 'cancelled';
        $pdo->prepare('
            UPDATE sx_ygo_competitions
            SET status=?, configuration_locked=IF(? = "pending_configuration", 0, configuration_locked), started_at=IF(? = "pending_configuration", NULL, started_at)
            WHERE id=? AND season_id=?
        ')->execute([$nextStatus, $nextStatus, $nextStatus, $competitionId, $seasonId]);
        ygo_record_competition_status_history($pdo, $seasonId, $competitionId, (string) ($competition['status'] ?? ''), $nextStatus, 'configure_competition', ['mode' => $mode, 'resume_from_cancelled' => $nextStatus === 'pending_configuration']);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'configure_competition', json_encode(['mode' => $mode, 'status' => $nextStatus, 'resume_from_cancelled' => $nextStatus === 'pending_configuration', 'before'=>$auditBefore, 'after'=>ygo_event_audit_snapshot($pdo,$competitionId)], JSON_UNESCAPED_UNICODE)]);
        return 0;
    }
    $qualifierDuelsPerDeck = max(2, min(5, (int) ($_POST['qualifier_duels_per_deck'] ?? ($competition['qualifier_duels_per_deck'] ?? 3))));

    $selectedDeckIds = $_POST['competition_deck_ids'] ?? [];
    if (!is_array($selectedDeckIds)) {
        $selectedDeckIds = [];
    }
    $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
    $currentDeckStmt = $pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1 ORDER BY sort_order, id');
    $currentDeckStmt->execute([$competitionId]);
    $currentCompetitionDeckIds = array_values(array_map('intval', $currentDeckStmt->fetchAll(PDO::FETCH_COLUMN)));
    $hasFixturesStmt=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');$hasFixturesStmt->execute([$competitionId]);
    $hasFixtures=(int)$hasFixturesStmt->fetchColumn()>0;
    $deckRosterLocked = $hasFixtures || !empty($competition['configuration_locked']) || in_array($currentStatusKey, ['completed', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'], true)
        || (!in_array($competitionType, ['cup', 'tournament', 'final'], true) && (!empty($competition['configuration_locked']) || in_array($currentStatusKey, ['active', 'activo'], true)));
    if ($deckRosterLocked) {
        $oldIds=$currentCompetitionDeckIds;$newIds=$selectedDeckIds;sort($oldIds);sort($newIds);
        if($oldIds!==$newIds) throw new InvalidArgumentException('No se pueden cambiar participantes de un evento ya programado.');
        $selectedDeckIds = $currentCompetitionDeckIds;
    } elseif ($deckPoolMode === 'division') {
        $selectedDeckIds = ygo_available_league_deck_ids($pdo, $competitionId, ygo_format_deck_ids($pdo, $eventRuleset));
    }
    $selectedDeckIds = ygo_validate_format_roster($pdo, $eventRuleset, $selectedDeckIds);
    if(ygo_admission_load($pdo,$competition)!==null)$selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds,false);
    $today = new DateTimeImmutable('today');
    $startDateRaw = (string) ($_POST['competition_start_on'] ?? '');
    $existingStartDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($competition['starts_on'] ?? '')) ? (string) $competition['starts_on'] : '';
    $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateRaw) ? $startDateRaw : ($existingStartDate ?: $today->format('Y-m-d'));
    if (new DateTimeImmutable($startDate) < $today) {
        $startDate = $existingStartDate !== '' ? $existingStartDate : $today->format('Y-m-d');
    }
    $pattern = (string) ($_POST['competition_schedule_pattern'] ?? 'consecutive');
    $allowedPatterns = ['consecutive', 'every_other_day', 'every_2_days', 'every_3_days', 'weekend'];
    if (!in_array($pattern, $allowedPatterns, true)) {
        $pattern = 'consecutive';
    }
    $qualifierMatchesPerDay = max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? ($competition['qualifier_matches_per_day'] ?? 1))));
    $matchFormat = (string) ($_POST['competition_match_format'] ?? ($competition['match_format'] ?? '1v1'));
    if(($competition['execution_model']??'')==='free_for_all'&&$matchFormat!=='ffa')throw new InvalidArgumentException('Esta ficha usa jugadores independientes, no lados1v1/2v2/3v3.');
    if(ygo_template_base_code((string)($competition['prompt_template']??''))==='pegasus_challenge'&&(!in_array($eventRuleset,['tcg_advanced','tcg_traditional'],true)||$matchFormat!=='1v1'))throw new InvalidArgumentException('Pegasus requiere modalidad individual y TCG Avanzado o Tradicional.');
    if (!in_array($matchFormat, ['1v1', '2v2', '3v3', 'ffa'], true)) {
        $matchFormat = '1v1';
    }
    $existingRuleCode = '';
    if (!empty($competition['game_rule_id'])) {
        $ruleCodeStmt = $pdo->prepare('SELECT code FROM sx_ygo_game_rules WHERE id = ? LIMIT 1');
        $ruleCodeStmt->execute([(int) $competition['game_rule_id']]);
        $existingRuleCode = (string) ($ruleCodeStmt->fetchColumn() ?: '');
    }
    $fallbackRuleId = (int) ($competition['game_rule_id'] ?? 0) ?: null;
    $gameRuleId = ygo_valid_game_rule_id($pdo, $seasonId, (int) ($_POST['competition_game_rule_id'] ?? 0), $fallbackRuleId, $competitionType, (string) ($competition['execution_model'] ?? 'manual'));
    if((int)($competition['best_of']??3)===1 && empty($_POST['competition_game_rule_id']) && empty($competition['game_rule_id'])) $gameRuleId=null;
    $uploadedLogo = ygo_store_event_logo_upload();
    $galleryLogo = ygo_valid_event_gallery_logo((string) ($_POST['competition_gallery_logo'] ?? ''), $competitionType);
    $removeLogo = isset($_POST['competition_remove_logo']);
    $defaultLogos = ygo_event_logo_gallery()[$competitionType] ?? [];
    $defaultLogo = (string) ($defaultLogos[0]['url'] ?? '');
    $logoUrl = $removeLogo ? $defaultLogo : ($uploadedLogo !== '' ? $uploadedLogo : ($galleryLogo !== '' ? $galleryLogo : ((string) ($competition['logo_url'] ?? '') ?: $defaultLogo)));
    $participantsPerSide = max(1, min(3, (int) substr($matchFormat, 0, 1)));
    $intervalDays = ['consecutive' => 1, 'every_other_day' => 2, 'every_2_days' => 3, 'every_3_days' => 4, 'weekend' => 0][$pattern];
    $selectedDeckCount = count($selectedDeckIds);
    $savedTeamRoster=ygo_team_kind($competition)!==''?ygo_team_load($pdo,$competition):null;
    $rosterGroups = $savedTeamRoster!==null ? array_column($savedTeamRoster['lineups'],'decks') : ygo_roster_groups_for_match($pdo, $selectedDeckIds, $participantsPerSide);
    $groupCount = count($rosterGroups);
    $maxQualifierDuels = max(0, $groupCount - 1);
    if ($competitionType === 'qualifier' && $maxQualifierDuels >= 2 && $qualifierDuelsPerDeck > $maxQualifierDuels) {
        $qualifierDuelsPerDeck = $maxQualifierDuels;
    }
    $minimumDecks = $competitionType === 'qualifier' ? max(4, $participantsPerSide * 2) : ($participantsPerSide * 2);
    $hasEnoughDecks = $selectedDeckCount >= $minimumDecks;
    $hasEvenTournamentRoster = $selectedDeckCount >= $participantsPerSide*2;
    $hasValidQualifierDuelCount = $competitionType !== 'qualifier' || ($maxQualifierDuels >= 2 && $qualifierDuelsPerDeck <= $maxQualifierDuels);
    $hasRequiredDivision = $competitionType !== 'league' || ($divisionId !== null && $divisionId > 0);
    $canRunCompetition = $hasRequiredDivision && $hasEnoughDecks && $groupCount >= 2 && $hasValidQualifierDuelCount && $hasEvenTournamentRoster;
    $expectedQualifierMatches = $competitionType === 'qualifier'
        ? (in_array((string)$competition['execution_model'],ygo_workflow_models(),true) ? intdiv($selectedDeckCount,2) : ygo_expected_qualifier_match_count($selectedDeckCount, $participantsPerSide, $qualifierDuelsPerDeck))
        : 0;
    $scheduledQualifierMatches = 0;
    if ($competitionType === 'qualifier') {
        $scheduledStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase IN ("qualifier","swiss") AND status <> "FINALIZADA" AND scheduled_on IS NOT NULL');
        $scheduledStmt->execute([$competitionId]);
        $scheduledQualifierMatches = (int) $scheduledStmt->fetchColumn();
    }
    $existingMatchStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"');
    $existingMatchStmt->execute([$competitionId]);
    $existingScheduledMatches = (int) $existingMatchStmt->fetchColumn();
    $startDateReached = new DateTimeImmutable($startDate) <= $today;
    $competitionGate = ygo_competition_gate($pdo, $competitionId);
    $canStartCompetition = $startDateReached && $canRunCompetition && $competitionGate['can_execute'] && ($competitionType !== 'qualifier' || ($expectedQualifierMatches > 0 && $scheduledQualifierMatches >= $expectedQualifierMatches));
    $status = $postedStatus;
    if ($postedStatus === 'cancelled') {
        $status = 'cancelled';
    } elseif ($competitionType === 'qualifier' && !$canRunCompetition) {
        $status = 'pending_configuration';
    }
    if ($status === 'cancelled') {
        $mode = 'save';
    } elseif (!$canRunCompetition && in_array($mode, ['start', 'schedule_qualifier', 'schedule_cup'], true)) {
        $status = 'pending_configuration';
    } elseif ($mode === 'start' && $canStartCompetition) {
        $status = 'active';
    } elseif ($mode === 'start' && $competitionType === 'qualifier' && !$canStartCompetition) {
        $status = $scheduledQualifierMatches > 0 ? 'pending_event' : 'pending_configuration';
    } elseif ($mode === 'schedule_qualifier' && $competitionType === 'qualifier' && $canRunCompetition) {
        $status = 'pending_event';
    } elseif ($mode === 'schedule_cup' && in_array($competitionType, ['cup', 'final'], true) && $canRunCompetition && !in_array(ygo_normalize_status_key($postedStatus), ['active', 'activo'], true)) {
        $status = 'pending_event';
    }
    $locked = !empty($competition['configuration_locked']);
    if ($locked) {
        $startDate = (string) (($competition['starts_on'] ?? '') ?: $startDate);
        $pattern = (string) (($competition['schedule_pattern'] ?? '') ?: $pattern);
        $intervalDays = (int) ($competition['schedule_interval_days'] ?? $intervalDays);
        $qualifierMatchesPerDay = max(1, min(5, (int) (($competition['qualifier_matches_per_day'] ?? 1) ?: 1)));
    }
    if ($mode === 'save') {
        $startDate = (string) (($competition['starts_on'] ?? '') ?: $startDate);
        $matchFormat = (string) (($competition['match_format'] ?? '') ?: '1v1');
        $participantsPerSide = max(1, min(3, (int) (($competition['participants_per_side'] ?? 1) ?: 1)));
        $qualifierDuelsPerDeck = max(2, min(5, (int) (($competition['qualifier_duels_per_deck'] ?? 3) ?: 3)));
        $qualifierMatchesPerDay = max(1, min(5, (int) (($competition['qualifier_matches_per_day'] ?? 1) ?: 1)));
        $pattern = (string) (($competition['schedule_pattern'] ?? '') ?: 'consecutive');
        $intervalDays = (int) ($competition['schedule_interval_days'] ?? 1);
        $gameRuleId = !empty($competition['game_rule_id']) ? (int) $competition['game_rule_id'] : null;
    }

    $pdo->beginTransaction();
    try {
        ygo_validate_league_roster($pdo, $competitionId, $selectedDeckIds);
        if (!$deckRosterLocked) {
            $pdo->prepare('UPDATE sx_ygo_competition_decks SET active = 0 WHERE competition_id = ?')->execute([$competitionId]);
            $deckInsert = $pdo->prepare('
                INSERT INTO sx_ygo_competition_decks (competition_id, deck_id, sort_order, active)
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), active=1
            ');
            foreach ($selectedDeckIds as $index => $deckId) {
                $deckInsert->execute([$competitionId, $deckId, $index + 1]);
            }
        }

        $shouldLock = $mode === 'start' && $canStartCompetition;
        $pdo->prepare('
            UPDATE sx_ygo_competitions
            SET division_id=?, name=?, type=?, event_ruleset=?, ruleset_version=?, accent=?, logo_url=?, rules=?, starts_on=?, status=?, match_format=?, participants_per_side=?, qualifier_duels_per_deck=?, qualifier_matches_per_day=?, deck_pool=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, lock_division_moves=?, schedule_strategy=?, schedule_pattern=?, schedule_interval_days=?, game_rule_id=?, configuration_locked=IF(? = 1, 1, configuration_locked), started_at=IF(? = 1, COALESCE(started_at, NOW()), started_at)
            WHERE id=? AND season_id=?
        ')->execute([
            $divisionId,
            $competitionName !== '' ? $competitionName : (string) ($competition['name'] ?? 'Evento'),
            $competitionType,
            $eventRuleset,
            $rulesetVersion,
            $accent,
            $logoUrl,
            $rules,
            $startDate,
            $status,
            $matchFormat,
            $participantsPerSide,
            $qualifierDuelsPerDeck,
            $qualifierMatchesPerDay,
            $deckPoolMode,
            $leaguePromotionSlots,
            $leagueRelegationSlots,
            $leaguePromotionTo,
            $leagueRelegationTo,
            $lockDivisionMoves,
            $shouldLock ? 'auto_event' : (string) (($competition['schedule_strategy'] ?? '') ?: 'manual'),
            $pattern,
            $intervalDays,
            $gameRuleId ?: null,
            $shouldLock ? 1 : 0,
            $shouldLock ? 1 : 0,
            $competitionId,
            $seasonId,
        ]);
        if (isset($_POST['division_movements']) && is_array($_POST['division_movements'])) {
            ygo_save_division_movements($pdo, $competitionId, $_POST['division_movements']);
        }
        if ($logoUrl !== '' && $logoUrl !== (string) ($competition['logo_url'] ?? '')) {
            ygo_record_logo_history($pdo, 'competition', $competitionId, $logoUrl, $uploadedLogo !== '' ? 'upload' : 'gallery');
        }

        $created = 0;
        if ($mode === 'schedule_qualifier' && $competitionType === 'qualifier' && $canRunCompetition) {
            $scheduleCompetition = array_merge($competition, ['id' => $competitionId, 'type' => $competitionType, 'division_id' => $divisionId ?: ($competition['division_id'] ?? 0), 'best_of' => $competition['best_of'] ?? 3]);
            $created = ygo_schedule_qualifier_duels($pdo, $scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierDuelsPerDeck, $qualifierMatchesPerDay);
            ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, 'qualifier');
        } elseif ($mode === 'schedule_cup' && in_array($competitionType, ['cup', 'final'], true) && $canRunCompetition && $existingScheduledMatches === 0) {
            $scheduleCompetition = array_merge($competition, ['id' => $competitionId, 'type' => $competitionType, 'division_id' => $divisionId ?: ($competition['division_id'] ?? 0), 'best_of' => $competition['best_of'] ?? 3]);
            $created = ygo_schedule_competition_by_model($pdo, $scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierMatchesPerDay);
            ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $competitionType);
        } elseif ($mode === 'start' && $canRunCompetition && $existingScheduledMatches === 0) {
            $scheduleCompetition = array_merge($competition, [
                'id' => $competitionId,
                'type' => $competitionType,
                'division_id' => $divisionId ?: ($competition['division_id'] ?? 0),
                'best_of' => $competition['best_of'] ?? 3,
                'execution_model' => $competition['execution_model'] ?? 'manual',
                'phase_structure' => $competition['phase_structure'] ?? 'NONE',
            ]);
            $created = ygo_schedule_competition_by_model($pdo, $scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierMatchesPerDay);
            if (in_array($competitionType, ['cup', 'final'], true) && $created < 1) {
                throw new RuntimeException('No fue posible generar la llave de la copa antes de comenzar.');
            }
            if ($created > 0 && in_array($competitionType, ['cup', 'final'], true)) {
                ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $competitionType);
            }
        }
        if ($created > 0) {
            if(ygo_fixed_program($pdo)) {
                if(ygo_team_kind($competition)==='' && !in_array((string)$competition['execution_model'],ygo_workflow_models(),true) && ygo_admission_load($pdo,$competition)===null)ygo_reflow_pending_competition($pdo,$competitionId,$startDate,$pattern,$qualifierMatchesPerDay);
                $newDates=$pdo->prepare('SELECT * FROM sx_ygo_matches WHERE competition_id=?');$newDates->execute([$competitionId]);
                ygo_season_validate_competition_schedule($pdo,$competitionId);
                foreach($newDates->fetchAll(PDO::FETCH_ASSOC) as $scheduled){
                    $valid=ygo_validate_match_schedule($pdo,(int)$scheduled['id'],$scheduled['scheduled_on'],$scheduled['starts_at'],$scheduled['venue']);
                    if(empty($valid['ok'])) throw new InvalidArgumentException('La programacion nueva no cumple las restricciones de fecha, horario o participantes. El calendario existente se conserva.');
                }
            } elseif(ygo_team_kind($competition)==='' && !in_array((string)$competition['execution_model'],ygo_workflow_models(),true) && ygo_admission_load($pdo,$competition)===null)ygo_rebalance_after_competition_schedule($pdo, $competitionId);
        }
        ygo_persist_competition_calendar_event($pdo, $seasonId, $competitionId);

        $conditionQualifierId = (int) ($_POST['condition_qualifier_id'] ?? 0);
        if(ygo_admission_load($pdo,$competition)!==null){ /* Typed admission owns its dependencies. */ }
        elseif (in_array($competitionType, ['league', 'tournament', 'world'], true)) {
            $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')->execute([$competitionId]);
            if ($conditionQualifierId > 0) {
                $qualifierStmt = $pdo->prepare("
                    SELECT c.id
                    FROM sx_ygo_competitions c
                    WHERE c.id=? AND c.season_id=? AND c.type='qualifier' AND c.active=1
                      AND (
                        LOWER(REPLACE(c.status, ' ', '_')) IN ('pending_event', 'pendiente_evento')
                        OR (
                          LOWER(REPLACE(c.status, ' ', '_')) = 'pending_configuration'
                          AND (SELECT COUNT(*) FROM sx_ygo_competition_decks cd WHERE cd.competition_id = c.id AND cd.active = 1) >= 4
                        )
                      )
                    LIMIT 1
                ");
                $qualifierStmt->execute([$conditionQualifierId, $seasonId]);
                $validQualifierId = (int) ($qualifierStmt->fetchColumn() ?: 0);
                if ($validQualifierId > 0) {
                    $pdo->prepare('
                        INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
                        VALUES (?, ?, ?, "completed_with_results", 0, 1)
                        ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
                    ')->execute([$seasonId, $competitionId, $validQualifierId]);
                }
            }
        } else {
            $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')->execute([$competitionId]);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'configure_competition', json_encode(['mode' => $mode, 'decks' => count($selectedDeckIds), 'deck_roster_locked' => $deckRosterLocked, 'matches' => $created, 'existing_scheduled_matches' => $existingScheduledMatches, 'reprogrammed' => $existingScheduledMatches > 0 && $created > 0, 'expected_matches' => $expectedQualifierMatches, 'start' => $startDate, 'pattern' => $pattern, 'match_format' => $matchFormat, 'participants_per_side' => $participantsPerSide, 'qualifier_duels_per_deck' => $qualifierDuelsPerDeck, 'qualifier_matches_per_day' => $qualifierMatchesPerDay, 'before'=>$auditBefore, 'after'=>ygo_event_audit_snapshot($pdo,$competitionId)], JSON_UNESCAPED_UNICODE)]);
        ygo_record_competition_status_history($pdo, $seasonId, $competitionId, (string) ($competition['status'] ?? ''), $status, 'configure_competition', ['mode' => $mode, 'matches' => $created]);
        ygo_season_validate_competition_schedule($pdo, $competitionId);
        $pdo->commit();
        return $created;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function ygo_delete_competition(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionDeletionModule::create($pdo)->execute(
        (int) ($_POST['competition_id'] ?? 0),
        (string) ($_POST['delete_confirmation'] ?? ''),
    );
}
