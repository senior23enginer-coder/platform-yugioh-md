<?php
declare(strict_types=1);

/**
 * post-actions: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_handle_post(PDO $pdo): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !ygo_can_edit()) return ygo_handle_post_serialized($pdo);
    return ygo_with_event_write_lock($pdo, fn() => ygo_handle_post_serialized($pdo));
}

function ygo_handle_post_serialized(PDO $pdo): ?string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }
    $action = (string) ($_POST['action'] ?? '');
    if(ygo_fixed_program($pdo) && in_array($action,['generate_leagues','generate_cups','generate_world','auto_schedule_qualifier','rebuild_qualifier_calendar'],true)) throw new InvalidArgumentException('Generador historico bloqueado: conserva el calendario fijo. Usa la ficha de un evento nuevo y un motor soportado.');
    ygo_season_initialize_context($pdo);
    if (!ygo_can_edit()) {
        return 'gestion_no_disponible';
    }
    if($action==='configure_statistics_global'){
        $mapping=[];foreach(['FULL','REDUCED','BONUS'] as $code)if(isset($_POST['statistics_k_'.$code])){$value=filter_var($_POST['statistics_k_'.$code],FILTER_VALIDATE_INT);if($value===false)throw new InvalidArgumentException('Importancia estadistica invalida.');$mapping[$code]=$value;}
        ygo_stats_configure_global($pdo,trim((string)($_POST['statistics_anchor']??'')),$mapping);
        ygo_stats_sync($pdo);return 'estadisticas_actualizadas';
    }
    if($action==='publish_statistics_cut'){
        ygo_stats_publish($pdo,(string)($_POST['statistics_kind']??''),(string)($_POST['statistics_scope']??''),(string)($_POST['statistics_cut']??''),trim((string)($_POST['statistics_reason']??'')));
        ygo_stats_sync($pdo);return 'estadisticas_actualizadas';
    }
    $seasonResult = ygo_season_handle_post($pdo, $action);
    if ($seasonResult !== null) return $seasonResult;
    $seasonError = ygo_season_guard_post($pdo, $action);
    if ($seasonError !== null) return $seasonError;
    if ($action === 'sync_offline_form') {
        $requestId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['offline_request_id'] ?? '')) ?: '';
        $payload = json_decode((string) ($_POST['offline_payload'] ?? ''), true);
        $allowedActions = ['save_deck','save_deck_analysis_profile','save_matchup_analysis','save_team','save_competition','configure_competition','save_match_schedule','save_division','save_settings'];
        if ($requestId === '' || !is_array($payload) || !in_array((string) ($payload['action'] ?? ''), $allowedActions, true)) return 'offline_invalido';
        $existing = $pdo->prepare('SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1');
        $existing->execute([$requestId]); $existingResult = $existing->fetchColumn();
        if ($existingResult !== false) return (string) $existingResult;
        $entityMap = [
            'save_deck' => ['sx_ygo_decks','deck_id'], 'save_deck_analysis_profile' => ['sx_ygo_deck_analysis_profiles','deck_id'], 'save_matchup_analysis' => ['sx_ygo_matchup_analyses','analysis_id'], 'save_team' => ['sx_ygo_teams','team_id'],
            'save_competition' => ['sx_ygo_competitions','competition_id'], 'configure_competition' => ['sx_ygo_competitions','competition_id'],
            'save_match_schedule' => ['sx_ygo_matches','schedule_match_id'], 'save_division' => ['sx_ygo_divisions','division_id'],
        ];
        $originalAction = (string) $payload['action'];
        $baseline = trim((string) ($payload['offline_entity_updated_at'] ?? ''));
        if ($baseline !== '' && isset($entityMap[$originalAction])) {
            [$table,$idField] = $entityMap[$originalAction]; $entityId = (int) ($payload[$idField] ?? 0);
            if ($entityId > 0 && ygo_column_exists($pdo,$table,'updated_at')) {
                $current = $pdo->prepare("SELECT updated_at FROM $table WHERE id=? LIMIT 1"); $current->execute([$entityId]);
                $currentUpdated = (string) ($current->fetchColumn() ?: '');
                if ($currentUpdated !== '' && $currentUpdated !== $baseline) return 'offline_conflicto';
            }
        }
        unset($payload['offline_entity_updated_at']);
        $outerPost = $_POST; $_POST = $payload;
        try { $result = ygo_handle_post($pdo) ?: 'offline_guardado'; } finally { $_POST = $outerPost; }
        $pdo->prepare('INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)')->execute([$requestId,0,hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE) ?: ''),$result]);
        return $result;
    }
    if ($action === 'save_settings') {
        $allowed = [
            'allow_early_results',
            'allow_overdue_results',
            'result_date_guard',
            'alert_days_ahead',
            'valid_weekdays',
            'allowed_start_time',
            'allowed_end_time',
            'max_matches_per_day',
            'allow_special_days',
            'special_days',
            'venues',
            'stats_minimum_sample',
            'tier_sample_minimum',
            'best_debut_min_matches',
            'meta_presence_weight',
            'tier_zero_presence',
            'tier_zero_winrate',
            'tier_one_score',
            'tier_two_score',
            'elo_k_factor',
            'live_refresh_seconds',
            'notification_minutes_before',
        ];
        foreach ($allowed as $key) {
            if ($key === 'valid_weekdays') {
                $rawDays = $_POST['valid_weekdays'] ?? [];
                $days = is_array($rawDays) ? $rawDays : explode(',', (string) $rawDays);
                $days = array_values(array_intersect(['1', '2', '3', '4', '5', '6', '0'], array_map('strval', $days)));
                $value = implode(',', $days);
                ygo_save_setting($pdo, $key, $value);
                continue;
            } else {
                $value = (string) ($_POST[$key] ?? '0');
            }
            if ($key === 'max_matches_per_day') {
                $value = (string) max(1, min(3, (int) $value));
            } elseif (in_array($key, ['alert_days_ahead', 'live_refresh_seconds', 'notification_minutes_before'], true)) {
                $value = (string) max(1, min(30, (int) $value));
            } elseif (in_array($key, ['stats_minimum_sample', 'tier_sample_minimum', 'best_debut_min_matches'], true)) {
                $value = (string) max(1, min(100, (int) $value));
            } elseif (in_array($key, ['meta_presence_weight', 'tier_zero_presence', 'tier_zero_winrate', 'tier_one_score', 'tier_two_score'], true)) {
                $value = (string) max(0, min(100, (float) $value));
            } elseif ($key === 'elo_k_factor') {
                $value = (string) max(1, min(128, (float) $value));
            } elseif (in_array($key, ['allowed_start_time', 'allowed_end_time'], true)) {
                $value = preg_match('/^\d{2}:\d{2}$/', $value) ? $value : ($key === 'allowed_start_time' ? '10:00' : '22:00');
            } elseif (in_array($key, ['special_days', 'venues'], true)) {
                $value = trim($value);
            } else {
                $value = $value === '1' ? '1' : '0';
            }
            ygo_save_setting($pdo, $key, $value);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['settings', ygo_current_season_id($pdo), 'save_settings', json_encode($_POST, JSON_UNESCAPED_UNICODE)]);
        ygo_sync_meta_history($pdo);
        ygo_sync_elo_history($pdo);
        ygo_sync_global_ranking_history($pdo);
        ygo_sync_event_summaries($pdo);
        ygo_sync_medal_unlocks($pdo);
        return 'configuracion_guardada';
    }
    if ($action === 'save_ui_labels') {
        $seasonId = ygo_current_season_id($pdo);
        $keys = $_POST['ui_label_key'] ?? [];
        $titles = $_POST['ui_label_title'] ?? [];
        $subtitles = $_POST['ui_label_subtitle'] ?? [];
        $actives = array_map('strval', (array) ($_POST['ui_label_active'] ?? []));
        $rows = max(count((array) $keys), count((array) $titles));
        $upsert = $pdo->prepare('
            INSERT INTO sx_ygo_ui_labels (season_id, label_key, title, subtitle, active)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), active=VALUES(active)
        ');
        for ($i = 0; $i < $rows; $i++) {
            $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($keys[$i] ?? '')));
            $title = trim((string) ($titles[$i] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            $upsert->execute([$seasonId, $key, $title, trim((string) ($subtitles[$i] ?? '')), in_array($key, $actives, true) ? 1 : 0]);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['ui_labels', $seasonId, 'save_ui_labels', json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE)]);
        return 'textos_guardados';
    }
    if ($action === 'save_catalog_items') {
        $seasonId = ygo_current_season_id($pdo);
        $ids = $_POST['catalog_id'] ?? [];
        $labels = $_POST['catalog_label'] ?? [];
        $icons = $_POST['catalog_icon'] ?? [];
        $accents = $_POST['catalog_accent'] ?? [];
        $sorts = $_POST['catalog_sort_order'] ?? [];
        $actives = array_map('strval', (array) ($_POST['catalog_active'] ?? []));
        $update = $pdo->prepare('UPDATE sx_ygo_catalog_items SET label=?, icon=?, accent=?, sort_order=?, active=? WHERE id=? AND season_id IN (0, ?)');
        foreach ((array) $ids as $index => $idRaw) {
            $id = (int) $idRaw;
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($labels[$index] ?? ''));
            if ($label === '') {
                continue;
            }
            $update->execute([
                $label,
                trim((string) ($icons[$index] ?? '')),
                trim((string) ($accents[$index] ?? '')),
                max(0, (int) ($sorts[$index] ?? 100)),
                in_array((string) $id, $actives, true) ? 1 : 0,
                $id,
                $seasonId,
            ]);
        }
        $newKey = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['new_catalog_key'] ?? '')));
        $newCode = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['new_catalog_code'] ?? '')));
        $newLabel = trim((string) ($_POST['new_catalog_label'] ?? ''));
        if ($newKey !== '' && $newCode !== '' && $newLabel !== '') {
            $pdo->prepare('
                INSERT INTO sx_ygo_catalog_items (season_id, catalog_key, item_code, label, icon, accent, sort_order, meta_json, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, "[]", 1)
                ON DUPLICATE KEY UPDATE label=VALUES(label), icon=VALUES(icon), accent=VALUES(accent), sort_order=VALUES(sort_order), active=1
            ')->execute([
                $seasonId,
                $newKey,
                $newCode,
                $newLabel,
                trim((string) ($_POST['new_catalog_icon'] ?? '')),
                trim((string) ($_POST['new_catalog_accent'] ?? '')),
                max(0, (int) ($_POST['new_catalog_sort_order'] ?? 100)),
            ]);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['catalog_items', $seasonId, 'save_catalog_items', json_encode(['updated' => count((array) $ids), 'new' => $newKey . ':' . $newCode], JSON_UNESCAPED_UNICODE)]);
        return 'opciones_guardadas';
    }
    if ($action === 'save_team') {
        $id = (int) ($_POST['team_id'] ?? 0);
        $existingLogo = '';
        if ($id > 0) {
            $logoStmt = $pdo->prepare('SELECT logo_url FROM sx_ygo_teams WHERE id = ? LIMIT 1');
            $logoStmt->execute([$id]);
            $existingLogo = (string) ($logoStmt->fetchColumn() ?: '');
        }
        $logoUrl = $existingLogo;
        $values = [
            trim((string) $_POST['team_name']),
            trim((string) $_POST['team_city']),
            $logoUrl,
            strtoupper(substr(trim((string) ($_POST['team_code'] ?? '')), 0, 12)),
            strtoupper(substr(trim((string) ($_POST['team_symbol'] ?? '')), 0, 24)),
        ];
        if ($values[0] !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_teams SET name=?, city=?, logo_url=?, short_code=?, symbol=? WHERE id=?')->execute([...$values, $id]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_teams (name, city, logo_url, short_code, symbol, active) VALUES (?, ?, ?, ?, ?, 1)')->execute($values);
                $id = (int) $pdo->lastInsertId();
            }
            $uploadedLogo = ygo_store_team_logo_upload($id);
            if ($uploadedLogo !== '') {
                $pdo->prepare('UPDATE sx_ygo_teams SET logo_url=? WHERE id=?')->execute([$uploadedLogo, $id]);
                ygo_record_logo_history($pdo, 'team', $id, $uploadedLogo);
            }
        }
        if ($competitionType === 'qualifier' && strtolower($competitionRuleset) === 'goat') {
            throw new RuntimeException('El formato GOAT se organiza por liga y divisiones; no admite clasificatorios.');
        }
        return 'equipo_' . $id;
    }
    if ($action === 'save_player') {
        $id = max(0, (int) ($_POST['player_id'] ?? 0));
        $name = trim((string) ($_POST['player_name'] ?? ''));
        $alias = trim((string) ($_POST['player_alias'] ?? ''));
        $teamId = max(0, (int) ($_POST['player_team_id'] ?? 0));
        $deckIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['player_deck_ids'] ?? [])), static fn($value) => $value > 0)));
        if ($name === '') {
            throw new RuntimeException('El nombre del jugador es obligatorio.');
        }
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_players SET team_id=?, display_name=?, alias=?, active=1 WHERE id=?')->execute([$teamId ?: null, $name, $alias, $id]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_players (team_id, display_name, alias, active) VALUES (?, ?, ?, 1)')->execute([$teamId ?: null, $name, $alias]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE sx_ygo_player_decks SET active=0 WHERE player_id=?')->execute([$id]);
            $link = $pdo->prepare('INSERT INTO sx_ygo_player_decks (player_id, deck_id, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE active=1');
            foreach ($deckIds as $deckId) $link->execute([$id, $deckId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES ("player", ?, "save_player", ?)')->execute([$id, json_encode(['team_id' => $teamId, 'deck_ids' => $deckIds], JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return 'jugador_' . $id;
    }
    if ($action === 'select_logo_history') {
        $entityType = trim((string) ($_POST['logo_entity_type'] ?? ''));
        $entityId = (int) ($_POST['logo_entity_id'] ?? 0);
        $logoUrl = trim((string) ($_POST['logo_history_url'] ?? ''));
        $allowed = ['deck' => ['sx_ygo_decks', 'cover_url'], 'team' => ['sx_ygo_teams', 'logo_url'], 'competition' => ['sx_ygo_competitions', 'logo_url']];
        if ($entityId > 0 && $logoUrl !== '' && isset($allowed[$entityType])) {
            $validStmt = $pdo->prepare('SELECT id FROM sx_ygo_logo_history WHERE entity_type=? AND entity_id=? AND logo_url=? LIMIT 1');
            $validStmt->execute([$entityType, $entityId, $logoUrl]);
            if ($validStmt->fetchColumn()) {
                [$table, $column] = $allowed[$entityType];
                $pdo->prepare("UPDATE {$table} SET {$column}=? WHERE id=?")->execute([$logoUrl, $entityId]);
                ygo_record_logo_history($pdo, $entityType, $entityId, $logoUrl, 'history');
            }
        }
        return 'logo_' . $entityType . '_' . $entityId;
    }
    if ($action === 'save_team_deck_links') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        if ($teamId > 0) {
            $teamExists = $pdo->prepare('SELECT id FROM sx_ygo_teams WHERE id=? AND active=1 LIMIT 1');
            $teamExists->execute([$teamId]);
            if ((int) ($teamExists->fetchColumn() ?: 0) > 0) {
                $addDeckIds = $_POST['team_add_deck_ids'] ?? [];
                $removeDeckIds = $_POST['team_remove_deck_ids'] ?? [];
                if (!is_array($addDeckIds)) {
                    $addDeckIds = [];
                }
                if (!is_array($removeDeckIds)) {
                    $removeDeckIds = [];
                }
                $addDeckIds = array_values(array_unique(array_filter(array_map('intval', $addDeckIds), static fn(int $id): bool => $id > 0)));
                $removeDeckIds = array_values(array_unique(array_filter(array_map('intval', $removeDeckIds), static fn(int $id): bool => $id > 0)));
                if ($addDeckIds) {
                    $placeholders = implode(',', array_fill(0, count($addDeckIds), '?'));
                    $pdo->prepare("UPDATE sx_ygo_decks SET team_id=? WHERE active=1 AND team_id IS NULL AND id IN ($placeholders)")
                        ->execute([$teamId, ...$addDeckIds]);
                }
                if ($removeDeckIds) {
                    $placeholders = implode(',', array_fill(0, count($removeDeckIds), '?'));
                    $pdo->prepare("UPDATE sx_ygo_decks SET team_id=NULL WHERE active=1 AND team_id=? AND id IN ($placeholders)")
                        ->execute([$teamId, ...$removeDeckIds]);
                }
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['team', $teamId, 'save_team_deck_links', json_encode(['add' => $addDeckIds, 'remove' => $removeDeckIds], JSON_UNESCAPED_UNICODE)]);
            }
        }
        return 'equipo_ficha';
    }
    if ($action === 'save_division') {
        $id = (int) ($_POST['division_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $selectedDeckIds = $_POST['division_deck_ids'] ?? [];
        if (!is_array($selectedDeckIds)) {
            $selectedDeckIds = [];
        }
        $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $deckId): bool => $deckId > 0)));
        if (count($selectedDeckIds) > 0 && count($selectedDeckIds) < 4) {
            return 'division_minimo_4_decks';
        }
        $code = strtoupper(trim((string) $_POST['division_code']));
        $name = trim((string) $_POST['division_name']);
        if ($code !== '' && $name !== '') {
            $promotionTo = ($_POST['division_promotion_to'] ?? '') === '' ? null : (int) $_POST['division_promotion_to'];
            $relegationTo = ($_POST['division_relegation_to'] ?? '') === '' ? null : (int) $_POST['division_relegation_to'];
            if ($id > 0 && $promotionTo === $id) {
                $promotionTo = null;
            }
            if ($id > 0 && $relegationTo === $id) {
                $relegationTo = null;
            }
            $leagueCompetitionId = ($_POST['division_league_competition_id'] ?? '') === '' ? null : (int) $_POST['division_league_competition_id'];
            $sourceQualifierId = ($_POST['division_source_qualifier_id'] ?? '') === '' ? null : (int) $_POST['division_source_qualifier_id'];
            $rankFrom = ($_POST['division_rank_from'] ?? '') === '' ? null : max(1, (int) $_POST['division_rank_from']);
            $rankTo = ($_POST['division_rank_to'] ?? '') === '' ? null : max(1, (int) $_POST['division_rank_to']);
            if ($rankFrom !== null && $rankTo !== null && $rankTo < $rankFrom) {
                [$rankFrom, $rankTo] = [$rankTo, $rankFrom];
            }
            $values = [
                $code,
                $name,
                trim((string) $_POST['division_cup']),
                max(1, (int) ($_POST['division_tier'] ?? 1)),
                max(0, (int) ($_POST['division_size'] ?? count($selectedDeckIds))),
                trim((string) $_POST['division_accent']),
                trim((string) $_POST['division_description']),
                ($_POST['division_starts_on'] ?? '') ?: null,
                ($_POST['division_ends_on'] ?? '') ?: null,
                max(0, (int) ($_POST['division_promotion_slots'] ?? 0)),
                max(0, (int) ($_POST['division_relegation_slots'] ?? 0)),
                $promotionTo,
                $relegationTo,
                $leagueCompetitionId,
                $sourceQualifierId,
                $rankFrom,
                $rankTo,
            ];
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_divisions SET code=?, name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?, starts_on=?, ends_on=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, league_competition_id=?, source_qualifier_id=?, rank_from=?, rank_to=? WHERE id=? AND season_id=?')
                    ->execute([...$values, $id, $seasonId]);
            } else {
                $pdo->prepare('
                    INSERT INTO sx_ygo_divisions
                        (season_id, code, name, cup_name, tier_order, deck_count, accent, description, starts_on, ends_on, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, league_competition_id, source_qualifier_id, rank_from, rank_to, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), cup_name=VALUES(cup_name), tier_order=VALUES(tier_order), deck_count=VALUES(deck_count), accent=VALUES(accent), description=VALUES(description), starts_on=VALUES(starts_on), ends_on=VALUES(ends_on), promotion_slots=VALUES(promotion_slots), relegation_slots=VALUES(relegation_slots), promotion_to_division_id=VALUES(promotion_to_division_id), relegation_to_division_id=VALUES(relegation_to_division_id), league_competition_id=VALUES(league_competition_id), source_qualifier_id=VALUES(source_qualifier_id), rank_from=VALUES(rank_from), rank_to=VALUES(rank_to), active=1
                ')->execute([$seasonId, ...$values]);
                $lookup = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id=? AND code=? LIMIT 1');
                $lookup->execute([$seasonId, $code]);
                $id = (int) $lookup->fetchColumn();
            }
            if ($id > 0) {
                $uploadedLogo = ygo_store_division_logo_upload($id);
                if ($uploadedLogo !== '') {
                    $pdo->prepare('UPDATE sx_ygo_divisions SET logo_url=? WHERE id=? AND season_id=?')->execute([$uploadedLogo, $id, $seasonId]);
                }
            }
            if ($id > 0 && $selectedDeckIds) {
                $placeholders = implode(',', array_fill(0, count($selectedDeckIds), '?'));
                $deckStmt = $pdo->prepare("UPDATE sx_ygo_decks SET division_id=? WHERE active=1 AND id IN ($placeholders)");
                $deckStmt->execute([$id, ...$selectedDeckIds]);
                $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count=(SELECT COUNT(*) FROM sx_ygo_decks WHERE division_id=? AND active=1) WHERE id=?')
                    ->execute([$id, $id]);
            } elseif ($id > 0 && $sourceQualifierId && $rankFrom && $rankTo) {
                ygo_apply_division_from_qualifier($pdo, $id);
            }
        }
        return 'division';
    }
    if ($action === 'save_match_schedule') {
        $matchId = (int) ($_POST['schedule_match_id'] ?? 0);
        if ($matchId > 0) {
            $matchScheduleStmt = $pdo->prepare('SELECT competition_id, phase, status, scheduled_on, starts_at, venue, judge, notes FROM sx_ygo_matches WHERE id = ? LIMIT 1');
            $matchScheduleStmt->execute([$matchId]);
            $matchSchedule = $matchScheduleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$matchSchedule) {
                return 'calendario_bloqueado_regla';
            }
            if (in_array(strtoupper((string) ($matchSchedule['status'] ?? '')), ['FINALIZADA', 'FINALIZADO', 'COMPLETED'], true)) {
                return 'calendario_bloqueado_finalizado';
            }
            $scheduledOn = ($_POST['scheduled_on'] ?? '') ?: null;
            $startsAt = ($_POST['starts_at'] ?? '') ?: null;
            $venue = trim((string) ($_POST['venue'] ?? ''));
            $scheduleGuard = ygo_validate_match_schedule($pdo, $matchId, $scheduledOn, $startsAt, $venue);
            if (!$scheduleGuard['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', $matchId, 'blocked_schedule_by_rules', json_encode($scheduleGuard, JSON_UNESCAPED_UNICODE)]);
                return 'calendario_bloqueado_regla';
            }
            $pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?, starts_at=?, venue=?, judge=?, notes=? WHERE id=?')
                ->execute([
                    $scheduledOn,
                    $startsAt,
                    $venue,
                    trim((string) ($_POST['judge'] ?? '')),
                    trim((string) ($_POST['match_notes'] ?? '')),
                    $matchId,
                ]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'reschedule_match', json_encode([
                    'before' => [
                        'scheduled_on' => $matchSchedule['scheduled_on'] ?? null,
                        'starts_at' => $matchSchedule['starts_at'] ?? null,
                        'venue' => $matchSchedule['venue'] ?? '',
                        'judge' => $matchSchedule['judge'] ?? '',
                        'notes' => $matchSchedule['notes'] ?? '',
                    ],
                    'after' => [
                        'scheduled_on' => $scheduledOn,
                        'starts_at' => $startsAt,
                        'venue' => $venue,
                        'judge' => trim((string) ($_POST['judge'] ?? '')),
                        'notes' => trim((string) ($_POST['match_notes'] ?? '')),
                    ],
                ], JSON_UNESCAPED_UNICODE)]);
            $matchCompetitionId = (int) ($matchSchedule['competition_id'] ?? 0);
            if ($matchCompetitionId > 0) {
                ygo_rebuild_competition_calendar_events_from_matches($pdo, ygo_current_season_id($pdo), $matchCompetitionId, (string) ($matchSchedule['phase'] ?? 'match'));
                $competitionTypeStmt = $pdo->prepare('SELECT type FROM sx_ygo_competitions WHERE id=? LIMIT 1');
                $competitionTypeStmt->execute([$matchCompetitionId]);
                if ((string) $competitionTypeStmt->fetchColumn() !== 'qualifier') {
                    ygo_reflow_active_qualifiers($pdo);
                }
            }
        }
        return 'calendario_reprogramado';
    }
    if ($action === 'generate_friendlies') {
        return 'amistosos_' . ygo_generate_friendlies($pdo);
    }
    if ($action === 'generate_leagues') {
        return 'ligas_' . ygo_generate_leagues($pdo);
    }
    if ($action === 'generate_cups') {
        return 'copas_' . ygo_generate_cups($pdo);
    }
    if ($action === 'generate_world') {
        return 'mundial_' . ygo_generate_world($pdo);
    }
    if ($action === 'auto_schedule_qualifier') {
        return 'clasificatorio_programado_' . ygo_auto_schedule_qualifier($pdo);
    }
    if ($action === 'rebuild_qualifier_calendar') {
        return 'clasificatorio_reconstruido_' . ygo_rebuild_qualifier_calendar($pdo);
    }
    if($action==='finalize_ffa'){$id=(int)($_POST['competition_id']??0);ygo_ffa_finalize($pdo,$id);return 'evento_configurado_'.$id;}
    if($action==='create_ffa_match'){
        $id=(int)($_POST['competition_id']??0);$ids=array_map('intval',$_POST['ffa_decks']??[]);
        ygo_ffa_create($pdo,$id,$ids,(string)($_POST['ffa_date']??''),(string)($_POST['ffa_time']??''));return 'evento_configurado_'.$id;
    }
    if($action==='save_ffa_action'){
        $id=(int)($_POST['ffa_match_id']??0);$command=['kind'=>(string)($_POST['ffa_kind']??'')];
        foreach(['deck_id','delta'] as $k)if(isset($_POST['ffa_'.$k])){$n=filter_var($_POST['ffa_'.$k],FILTER_VALIDATE_INT);if($n===false)throw new InvalidArgumentException('Valor de accion invalido.');$command[$k]=$n;}
        if(isset($_POST['ffa_reason']))$command['reason']=(string)$_POST['ffa_reason'];
        ygo_ffa_command($pdo,$id,(int)($_POST['ffa_revision']??-1),(string)($_POST['ffa_request']??''),$command);
        $q=$pdo->prepare('SELECT competition_id FROM sx_ygo_ffa_matches WHERE id=?');$q->execute([$id]);return 'evento_configurado_'.(int)$q->fetchColumn();
    }
    if($action==='save_team_lineups'){
        $rows=ygo_team_decode_payload((string)($_POST['team_lineups_json']??'[]'));
        $id=(int)($_POST['competition_id']??0);ygo_team_save($pdo,$id,$rows);return 'evento_configurado_'.$id;
    }
    if($action==='save_team_result'){
        $rows=ygo_team_decode_payload((string)($_POST['team_sequences_json']??'[]'));
        $mid=(int)($_POST['match_id']??0);$c=ygo_competition_for_match($pdo,$mid);ygo_team_save_result($pdo,$mid,$rows,(int)($_POST['team_revision']??0));return 'evento_configurado_'.(int)($c['id']??0);
    }
    if(in_array($action,['freeze_event_card_rules','save_event_card_rules'],true))throw new InvalidArgumentException('El control de cartas, pools y elecciones de Draft fue retirado.');
    if($action==='save_event_admission'){
        try{$rows=json_decode((string)($_POST['admission_sources_json']??'[]'),true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new InvalidArgumentException('Las fuentes recibidas no tienen un formato valido.');}
        if(!is_array($rows))throw new InvalidArgumentException('Fuentes invalidas.');
        $capacity=filter_var($_POST['admission_capacity']??0,FILTER_VALIDATE_INT);if($capacity===false)throw new InvalidArgumentException('El total de plazas debe ser entero.');
        $id=(int)($_POST['competition_id']??0);ygo_admission_save($pdo,$id,$rows,$capacity);
        return 'evento_configurado_'.$id;
    }
    if ($action === 'configure_competition') {
        try {
            ygo_configure_competition($pdo);
        } catch (InvalidArgumentException $error) {
            $_SESSION['ygo_season_error'] = $error->getMessage();
            return 'evento_temporada_bloqueada';
        }
        return 'evento_configurado_' . max(0, (int) ($_POST['competition_id'] ?? 0));
    }
    if ($action === 'finalize_competition') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $stmt = $pdo->prepare("SELECT id, status FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1");
        $stmt->execute([$competitionId, $seasonId]);
        $competition = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($competition) {
            $currentStatus = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
            $ready = ygo_competition_ready_to_finish($pdo, $competitionId);
            if ($ready['ok'] && in_array($currentStatus, ['active', 'activo', 'pending_event', 'pendiente_evento'], true)) {
                $pdo->prepare("UPDATE sx_ygo_competitions SET status='completed' WHERE id=? AND season_id=?")->execute([$competitionId, $seasonId]);
                ygo_persist_competition_standings($pdo, $seasonId, $competitionId, 'finalize_competition');
                $divisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND source_qualifier_id = ? AND active = 1');
                $divisionStmt->execute([$seasonId, $competitionId]);
                $divisionMoves = 0;
                foreach (array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN)) as $divisionId) {
                    $divisionMoves += ygo_apply_division_from_qualifier($pdo, $divisionId);
                }
                $leagueMoves = ygo_apply_league_division_movements($pdo, $competitionId);
                $honors = ygo_record_competition_honor($pdo, $competitionId);
                ygo_record_competition_status_history($pdo, $seasonId, $competitionId, (string) ($competition['status'] ?? ''), 'completed', 'finalize_competition', $ready);
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['competition', $competitionId, 'finalize_competition', json_encode($ready + ['division_moves' => $divisionMoves, 'league_moves' => $leagueMoves, 'honors' => $honors], JSON_UNESCAPED_UNICODE)]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['competition', $competitionId, 'blocked_finalize_competition', json_encode($ready + ['status' => $currentStatus], JSON_UNESCAPED_UNICODE)]);
            }
        }
        return 'evento_configurado_' . $competitionId;
    }
    if ($action === 'revert_event_stage') {
        $competitionId=(int)($_POST['competition_id']??0);
        ygo_workflow_revert($pdo,$competitionId,(int)($_POST['expected_round']??0),(string)($_POST['recovery_reason']??''),(string)($_POST['recovery_token']??''));
        return 'evento_configurado_'.$competitionId;
    }
    if ($action === 'advance_competition') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        return 'evento_avanzado_' . $competitionId . '_' . ygo_advance_competition_phase($pdo, $competitionId);
    }
    if ($action === 'apply_qualifier_divisions') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $typeStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND type = 'qualifier' AND active = 1");
        $typeStmt->execute([$competitionId, $seasonId]);
        if ((int) $typeStmt->fetchColumn() < 1) {
            return 'divisiones_aplicadas_' . $competitionId . '_0';
        }
        $divisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND source_qualifier_id = ? AND active = 1');
        $divisionStmt->execute([$seasonId, $competitionId]);
        $moves = 0;
        foreach (array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN)) as $divisionId) {
            $moves += ygo_apply_division_from_qualifier($pdo, $divisionId);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$competitionId,'apply_qualifier_divisions',json_encode(['moved'=>$moves],JSON_UNESCAPED_UNICODE)]);
        return 'divisiones_aplicadas_' . $competitionId . '_' . $moves;
    }
    if ($action === 'apply_league_movements') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $typeStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND type = 'league' AND active = 1");
        $typeStmt->execute([$competitionId, $seasonId]);
        if ((int) $typeStmt->fetchColumn() < 1) {
            return 'movimientos_liga_' . $competitionId . '_0';
        }
        return 'movimientos_liga_' . $competitionId . '_' . ygo_apply_league_division_movements($pdo, $competitionId);
    }
    if ($action === 'recalculate_competition_standings') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $exists = 0;
        if ($competitionId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 AND type NOT IN ('cup', 'final') LIMIT 1");
            $stmt->execute([$competitionId, $seasonId]);
            $exists = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($exists > 0) {
            ygo_persist_competition_standings($pdo, $seasonId, $competitionId, 'manual_recalculate');
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition', $competitionId, 'recalculate_standings', json_encode(['source' => 'manual_button'], JSON_UNESCAPED_UNICODE)]);
        }
        return 'ranking_recalculado_' . $competitionId . '_' . ($exists > 0 ? 1 : 0);
    }
    if ($action === 'archive_qa_codex_data') {
        $seasonId = ygo_current_season_id($pdo);
        $counts = ['competitions' => 0, 'divisions' => 0, 'decks' => 0, 'teams' => 0];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE season_id=? AND name LIKE 'QA Codex %' AND name <> 'Clasificatorio inicial MD'");
            $stmt->execute([$seasonId]);
            $counts['competitions'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_divisions SET active=0 WHERE season_id=? AND name LIKE 'QA Codex %'");
            $stmt->execute([$seasonId]);
            $counts['divisions'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_decks SET active=0 WHERE name LIKE 'QA Codex %'");
            $stmt->execute();
            $counts['decks'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_teams SET active=0 WHERE name LIKE 'QA Codex %'");
            $stmt->execute();
            $counts['teams'] = $stmt->rowCount();
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['qa', $seasonId, 'archive_qa_codex_data', json_encode($counts, JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return 'qa_codex_archivado_' . array_sum($counts);
    }
    if ($action === 'delete_competition') {
        return 'evento_eliminado_' . ygo_delete_competition($pdo);
    }
    if ($action === 'delete_manual_friendly') {
        $matchId = max(0, (int) ($_POST['friendly_match_id'] ?? 0));
        $matchStmt = $pdo->prepare("SELECT id, deck_a_id, deck_b_id, scheduled_on, starts_at, status FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL LIMIT 1");
        $matchStmt->execute([$matchId]);
        $manualFriendly = $matchStmt->fetch(PDO::FETCH_ASSOC);
        if (!$manualFriendly) {
            return 'amistoso_no_eliminado';
        }
        $pdo->beginTransaction();
        try {
            foreach (['sx_ygo_sync_requests', 'sx_ygo_turn_audit', 'sx_ygo_live_duel_state', 'sx_ygo_match_snapshots', 'sx_ygo_elo_history', 'sx_ygo_match_results'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE match_id = ?")->execute([$matchId]);
            }
            $pdo->prepare('DELETE FROM sx_ygo_medal_unlocks WHERE match_id = ?')->execute([$matchId]);
            $pdo->prepare('DELETE FROM sx_ygo_checkins WHERE match_id = ?')->execute([$matchId]);
            $pdo->prepare("DELETE FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL")->execute([$matchId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'delete_manual_friendly', json_encode($manualFriendly, JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'amistoso_eliminado';
    }
    if ($action === 'advance_phase') {
        $phase = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['phase'] ?? 'cup')));
        if ($phase === '') {
            $phase = 'cup';
        }
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        return 'avance_' . $phase . '_' . ygo_advance_phase($pdo, $phase, $competitionId > 0 ? $competitionId : null);
    }
    if ($action === 'create_match') {
        $divisionId = (int) ($_POST['manual_division_id'] ?? ygo_first_division_id($pdo));
        $phase = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['manual_phase'] ?? 'league')));
        if ($phase === '') {
            $phase = 'league';
        }
        $round = max(1, (int) ($_POST['manual_round'] ?? 1));
        $table = max(1, (int) ($_POST['manual_table'] ?? 1));
        $deckA = (int) ($_POST['manual_deck_a_id'] ?? 0);
        $deckB = (int) ($_POST['manual_deck_b_id'] ?? 0);
        if ($deckA > 0 && $deckB > 0 && $deckA !== $deckB) {
            $competitionId = ($_POST['manual_competition_id'] ?? '') === '' ? null : (int) $_POST['manual_competition_id'];
            if($competitionId){$teamCheck=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');$teamCheck->execute([$competitionId]);if(ygo_team_kind($manualEvent=$teamCheck->fetch(PDO::FETCH_ASSOC) ?: [])!==''||($manualEvent['execution_model']??'')==='free_for_all')throw new InvalidArgumentException('Programa esta modalidad desde su ficha con las alineaciones fijadas.');}
            $format = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['manual_match_format'] ?? '1v1')));
            $bestOf = max(1, min(10, (int) ($_POST['manual_best_of'] ?? 5)));
            $participantsPerSide = max(1, min(3, (int) ($_POST['manual_participants_per_side'] ?? 1)));
            if (preg_match('/^([123])v\1$/', $format, $matchParts)) {
                $participantsPerSide = (int) $matchParts[1];
            }
            $deckC = ($_POST['manual_deck_c_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_c_id'];
            $deckD = ($_POST['manual_deck_d_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_d_id'];
            $deckE = ($_POST['manual_deck_e_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_e_id'];
            $deckF = ($_POST['manual_deck_f_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_f_id'];
            $manualRuleset = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['manual_ruleset_code'] ?? '')));
            $manualGameRuleId = $competitionId === null ? ygo_valid_match_game_rule_id($pdo, ygo_current_season_id($pdo), (int) ($_POST['manual_game_rule_id'] ?? 0), $phase) : null;
            $deckValidation = ygo_match_deck_validation($pdo, $competitionId, $divisionId, [$deckA, $deckB, $deckC, $deckD, $deckE, $deckF], $participantsPerSide);
            if ($deckValidation['ok'] && $competitionId === null && $manualRuleset !== '') {
                $rulesetDeckIds = ygo_deck_ids_for_ruleset($pdo, $manualRuleset);
                $selectedDeckIds = array_values(array_filter([$deckA, $deckB, $deckC, $deckD, $deckE, $deckF]));
                if (!$rulesetDeckIds || array_diff($selectedDeckIds, $rulesetDeckIds)) {
                    $deckValidation = ['ok' => false, 'message' => 'Hay decks que no estan asociados al formato seleccionado.'];
                }
            }
            if (!$deckValidation['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', null, 'blocked_create_match_by_deck_pool', json_encode(['competition_id' => $competitionId, 'division_id' => $divisionId, 'deck_ids' => [$deckA, $deckB, $deckC, $deckD, $deckE, $deckF], 'message' => $deckValidation['message']], JSON_UNESCAPED_UNICODE)]);
                return 'partido_bloqueado_pool';
            }
            $manualScheduledOn = ($_POST['manual_scheduled_on'] ?? '') ?: null;
            $manualStartsAt = ($_POST['manual_starts_at'] ?? '') ?: null;
            $manualVenue = trim((string) ($_POST['manual_venue'] ?? ''));
            $scheduleGuard = ygo_validate_match_schedule($pdo, 0, $manualScheduledOn, $manualStartsAt, $manualVenue);
            if (!$scheduleGuard['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', null, 'blocked_create_match_by_rules', json_encode($scheduleGuard, JSON_UNESCAPED_UNICODE)]);
                return 'partido_bloqueado_regla';
            }
            if($competitionId){
                $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1');$q->execute([$competitionId]);$manualEvent=$q->fetch(PDO::FETCH_ASSOC);
                if(!$manualEvent)throw new InvalidArgumentException('Evento inexistente.');
                if(in_array($manualEvent['execution_model'],ygo_workflow_models(),true))throw new InvalidArgumentException('Este sistema genera sus etapas desde la ficha; no admite cruces manuales adicionales.');
                ygo_require_supported_model(ygo_team_context($pdo,$manualEvent));
            }
            if ($competitionId === null && $phase === 'friendly') {
                $nextTableStmt = $pdo->query("SELECT COALESCE(MAX(table_number), 0) + 1 FROM sx_ygo_matches WHERE competition_id IS NULL AND phase = 'friendly'");
                $table = max(1, (int) $nextTableStmt->fetchColumn());
            }
            $displayOrder = (($round - 1) * 100) + $table;
            $pdo->prepare("
                INSERT INTO sx_ygo_matches (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, judge, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE competition_id=VALUES(competition_id), game_rule_id=VALUES(game_rule_id), deck_a_id=VALUES(deck_a_id), deck_b_id=VALUES(deck_b_id), deck_c_id=VALUES(deck_c_id), deck_d_id=VALUES(deck_d_id), deck_e_id=VALUES(deck_e_id), deck_f_id=VALUES(deck_f_id), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), bracket_round_label=VALUES(bracket_round_label), bracket_slot=VALUES(bracket_slot), bracket_side=VALUES(bracket_side), display_order=VALUES(display_order), scheduled_on=VALUES(scheduled_on), starts_at=VALUES(starts_at), venue=VALUES(venue), judge=VALUES(judge), notes=VALUES(notes)
            ")->execute([
                $divisionId > 0 ? $divisionId : null, $competitionId, $manualGameRuleId, $phase, $format, $bestOf, $participantsPerSide,
                $round, $table, 'Ronda ' . $round, $table, $displayOrder, $deckA, $deckB, $deckC, $deckD, $deckE, $deckF,
                $manualScheduledOn,
                $manualStartsAt,
                $manualVenue,
                trim((string) ($_POST['manual_judge'] ?? '')),
                trim((string) ($_POST['manual_notes'] ?? '')),
            ]);
            if ($competitionId) {
                ygo_rebuild_competition_calendar_events_from_matches($pdo, ygo_current_season_id($pdo), (int) $competitionId, $phase);
            }
        } else {
            return 'partido_invalido';
        }
        return 'partido_manual';
    }
    if ($action === 'save_deck_card') {
        $deckId = (int) ($_POST['card_deck_id'] ?? 0);
        $cardName = trim((string) ($_POST['card_name'] ?? ''));
        if ($deckId > 0 && $cardName !== '') {
            $pdo->prepare('INSERT INTO sx_ygo_deck_cards (deck_id, card_name, role, quantity, section, notes) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$deckId, $cardName, trim((string) ($_POST['card_role'] ?? '')), max(1, (int) ($_POST['card_quantity'] ?? 1)), trim((string) ($_POST['card_section'] ?? 'main')), trim((string) ($_POST['card_notes'] ?? ''))]);
        }
        return 'carta_deck';
    }
    if ($action === 'import_deck_cards') {
        $deckId = (int) ($_POST['import_card_deck_id'] ?? 0);
        $raw = trim((string) ($_POST['deck_card_rows'] ?? ''));
        $replace = !empty($_POST['replace_deck_cards']);
        if ($deckId > 0 && $raw !== '') {
            $rows = preg_split('/\R/', $raw) ?: [];
            $valid = [];
            foreach ($rows as $line) {
                $columns = str_getcsv(trim($line), ';');
                if (count($columns) < 3) $columns = str_getcsv(trim($line), ',');
                $section = strtolower(trim((string) ($columns[0] ?? 'main')));
                $quantity = max(1, min(3, (int) ($columns[1] ?? 1)));
                $name = mb_substr(trim((string) ($columns[2] ?? '')), 0, 180);
                if (!in_array($section, ['main','extra','side','tech'], true) || $name === '' || strtolower($name) === 'nombre') continue;
                $valid[] = [$deckId,$name,mb_substr(trim((string)($columns[3] ?? '')),0,120),$quantity,$section,mb_substr(trim((string)($columns[4] ?? '')),0,255)];
            }
            if ($valid) {
                $pdo->beginTransaction();
                try {
                    if ($replace) $pdo->prepare('DELETE FROM sx_ygo_deck_cards WHERE deck_id=?')->execute([$deckId]);
                    $insert = $pdo->prepare('INSERT INTO sx_ygo_deck_cards (deck_id,card_name,role,quantity,section,notes) VALUES (?,?,?,?,?,?)');
                    foreach ($valid as $row) $insert->execute($row);
                    $pdo->commit();
                } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
            }
        }
        return 'cartas_importadas';
    }
    if ($action === 'save_deck_media') {
        $deckId = (int) ($_POST['media_deck_id'] ?? 0);
        $url = trim((string) ($_POST['media_url'] ?? ''));
        if ($deckId > 0 && $url !== '') {
            $pdo->prepare('INSERT INTO sx_ygo_deck_media (deck_id, media_type, url, title) VALUES (?, ?, ?, ?)')
                ->execute([$deckId, trim((string) ($_POST['media_type'] ?? 'image')), $url, trim((string) ($_POST['media_title'] ?? ''))]);
        }
        return 'media_deck';
    }
    if ($action === 'save_honor') {
        $seasonId=ygo_current_season_id($pdo);$eventId=max(0,(int)($_POST['honor_competition_id']??0));
        $champion=max(0,(int)($_POST['honor_champion_deck_id']??0));$runner=max(0,(int)($_POST['honor_runner_deck_id']??0));$notes=trim((string)($_POST['honor_notes']??''));
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try{
            if($champion<1||$champion===$runner)throw new InvalidArgumentException('Selecciona un campeon identificado y distinto del subcampeon.');
            if($eventId>0){
                $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND season_id=? FOR UPDATE');$q->execute([$eventId,$seasonId]);$event=$q->fetch(PDO::FETCH_ASSOC);
                if(!$event||!in_array(ygo_normalize_status_key((string)$event['status']),['completed','closed'],true))throw new InvalidArgumentException('El titulo requiere un evento finalizado confirmado.');
                if(($event['execution_model']??'')!=='manual')throw new InvalidArgumentException('El campeon de este sistema se determina desde su flujo competitivo; no se sustituye desde Palmares.');
                if($notes==='')throw new InvalidArgumentException('La adjudicacion manual requiere justificar la evidencia del titulo.');
                $q=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id=?');$q->execute([$eventId]);if($q->fetchColumn())throw new InvalidArgumentException('Esta edicion ya tiene un titulo. Su correccion requiere una revision controlada.');
                $q=$pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 UNION SELECT deck_a_id FROM sx_ygo_matches WHERE competition_id=? UNION SELECT deck_b_id FROM sx_ygo_matches WHERE competition_id=?');$q->execute([$eventId,$eventId,$eventId]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
                if(!in_array($champion,$allowed,true)||($runner&&!in_array($runner,$allowed,true)))throw new InvalidArgumentException('El titulo solo puede atribuirse a participantes de esta edicion.');
                ygo_validate_format_roster($pdo,(string)$event['event_ruleset'],array_values(array_filter([$champion,$runner])));
            }
            $q=$pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=?');$q->execute([$champion]);$championTeam=$q->fetchColumn();if(!$championTeam)throw new InvalidArgumentException('El campeon requiere una identidad equipo/deck confirmada.');
            $runnerTeam=null;if($runner){$q->execute([$runner]);$runnerTeam=$q->fetchColumn();if(!$runnerTeam)throw new InvalidArgumentException('Subcampeon no identificado.');}
            $pdo->prepare('INSERT INTO sx_ygo_honors(season_id,competition_id,champion_deck_id,champion_team_id,runner_up_deck_id,runner_up_team_id,title,notes) VALUES(?,?,?,?,?,?,?,?)')->execute([$seasonId,$eventId?:null,$champion,(int)$championTeam,$runner?:null,$runnerTeam?:null,trim((string)($_POST['honor_title']??'Campeon')),$notes]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$eventId,'manual_honor_confirmed',json_encode(['champion_deck_id'=>$champion,'champion_team_id'=>(int)$championTeam,'evidence'=>$notes],JSON_UNESCAPED_UNICODE)]);
            if($owns)$pdo->commit();return 'palmares';
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    if ($action === 'save_checkin') {
        $competitionId = max(0, (int) ($_POST['checkin_competition_id'] ?? 0));
        $matchId = max(0, (int) ($_POST['checkin_match_id'] ?? 0));
        $entityType = strtolower(trim((string) ($_POST['checkin_entity_type'] ?? 'guest')));
        $entityType = in_array($entityType, ['deck', 'team', 'player', 'judge', 'guest'], true) ? $entityType : 'guest';
        $entityId = max(0, (int) ($_POST['checkin_entity_id'] ?? 0));
        $displayName = mb_substr(trim((string) ($_POST['checkin_display_name'] ?? '')), 0, 160);
        $checkinCode = mb_substr(trim((string) ($_POST['checkin_code'] ?? '')), 0, 80);
        if ($displayName !== '') {
            $pdo->prepare('INSERT INTO sx_ygo_checkins (competition_id, match_id, entity_type, entity_id, display_name, checkin_code, source) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE checked_in_at=CURRENT_TIMESTAMP, checkin_code=VALUES(checkin_code), source=VALUES(source)')
                ->execute([$competitionId ?: null, $matchId ?: null, $entityType, $entityId ?: null, $displayName, $checkinCode ?: null, $checkinCode !== '' ? 'qr' : 'manual']);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['checkin', $matchId ?: $competitionId, 'participant_checkin', json_encode(['competition_id' => $competitionId, 'match_id' => $matchId, 'entity_type' => $entityType, 'entity_id' => $entityId, 'display_name' => $displayName], JSON_UNESCAPED_UNICODE)]);
        }
        return 'estadisticas';
    }
    if ($action === 'save_match_result') {
        $matchId = (int) ($_POST['match_id'] ?? 0);
        if(ygo_team_kind(ygo_competition_for_match($pdo,$matchId))!=='')throw new InvalidArgumentException('Usa Resultados por equipo para registrar cada puesto o la serie Tag compartida.');
        $offlineRequestId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['offline_request_id'] ?? '')) ?: '';
        if ($offlineRequestId !== '') {
            $syncedRequest = $pdo->prepare('SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1');
            $syncedRequest->execute([$offlineRequestId]);
            $syncedResult = $syncedRequest->fetchColumn();
            if ($syncedResult !== false) return (string) $syncedResult;
        }
        $values = [];
        for ($i = 1; $i <= 5; $i++) {
            $value = (string) ($_POST["d$i"] ?? '');
            $values[] = in_array($value, ['A', 'B', 'E'], true) ? $value : '';
        }
        if ($matchId > 0) {
            $diceRoll = trim((string) ($_POST['result_dice_roll'] ?? ''));
            $turnChoice = trim((string) ($_POST['result_turn_choice'] ?? ''));
            $duelDetails = trim((string) ($_POST['result_duel_details'] ?? ''));
            $dicePayload = [];
            if ($diceRoll !== '') {
                $decodedDice = json_decode($diceRoll, true);
                $dicePayload = is_array($decodedDice) ? $decodedDice : [];
            }
            $diceWinner = strtoupper((string) ($dicePayload['winner'] ?? ''));
            $turnChosenBy = strtoupper((string) ($dicePayload['chosen_by'] ?? $diceWinner));
            $turnDecision = strtolower((string) ($dicePayload['choice'] ?? ''));
            $startsPlayer = strtoupper((string) ($dicePayload['starts'] ?? ''));
            $diceWinner = in_array($diceWinner, ['A', 'B'], true) ? $diceWinner : null;
            $turnChosenBy = in_array($turnChosenBy, ['A', 'B'], true) ? $turnChosenBy : null;
            $turnDecision = in_array($turnDecision, ['primero', 'segundo', 'first', 'second'], true) ? ($turnDecision === 'first' ? 'primero' : ($turnDecision === 'second' ? 'segundo' : $turnDecision)) : null;
            $startsPlayer = in_array($startsPlayer, ['A', 'B'], true) ? $startsPlayer : null;
            if ($duelDetails !== '') {
                $decodedDetails = json_decode($duelDetails, true);
                if (is_array($decodedDetails)) {
                    foreach ($decodedDetails as $detailIndex => $detailRow) {
                        if (!is_array($detailRow)) {
                            unset($decodedDetails[$detailIndex]);
                            continue;
                        }
                        $detailChosenBy = strtoupper((string) ($detailRow['turn_chosen_by'] ?? ''));
                        $detailDecision = strtolower((string) ($detailRow['turn_decision'] ?? ''));
                        $detailStarts = strtoupper((string) ($detailRow['starts_player'] ?? ''));
                        $decodedDetails[$detailIndex]['turn_chosen_by'] = in_array($detailChosenBy, ['A', 'B'], true) ? $detailChosenBy : '';
                        $decodedDetails[$detailIndex]['turn_decision'] = in_array($detailDecision, ['primero', 'segundo', 'first', 'second'], true) ? ($detailDecision === 'first' ? 'primero' : ($detailDecision === 'second' ? 'segundo' : $detailDecision)) : '';
                        $decodedDetails[$detailIndex]['starts_player'] = in_array($detailStarts, ['A', 'B'], true) ? $detailStarts : '';
                        $decodedDetails[$detailIndex]['dice_a'] = max(0, min(6, (int) ($detailRow['dice_a'] ?? 0)));
                        $decodedDetails[$detailIndex]['dice_b'] = max(0, min(6, (int) ($detailRow['dice_b'] ?? 0)));
                        $detailDiceWinner = strtoupper((string) ($detailRow['dice_winner'] ?? $detailRow['turn_chosen_by'] ?? ''));
                        $decodedDetails[$detailIndex]['dice_winner'] = in_array($detailDiceWinner, ['A', 'B'], true) ? $detailDiceWinner : '';
                        $turnReason = strtolower(trim((string) ($detailRow['turn_reason'] ?? '')));
                        $decodedDetails[$detailIndex]['turn_reason'] = in_array($turnReason, ['dice', 'previous_duel_loss', 'draw_reroll', 'draw_reroll_unrecorded'], true) ? $turnReason : '';
                        $decodedDetails[$detailIndex]['notes'] = mb_substr(trim((string) ($detailRow['notes'] ?? '')), 0, 500);
                        $method = strtolower(trim((string) ($detailRow['defeat_method'] ?? '')));
                        $decodedDetails[$detailIndex]['defeat_method'] = in_array($method, ['life_points', 'surrender', 'time_limit', 'deck_out', 'effect_win'], true) ? $method : '';
                        $surrenderedBy = strtoupper(trim((string) ($detailRow['surrendered_by'] ?? '')));
                        $decodedDetails[$detailIndex]['surrendered_by'] = in_array($surrenderedBy, ['A', 'B'], true) ? $surrenderedBy : '';
                        $decodedDetails[$detailIndex]['surrendered_at'] = mb_substr(trim((string) ($detailRow['surrendered_at'] ?? '')), 0, 40);
                        $decodedDetails[$detailIndex]['turn_count'] = max(0, min(999, (int) ($detailRow['turn_count'] ?? 0)));
                        $decodedDetails[$detailIndex]['brick_a'] = !empty($detailRow['brick_a']);
                        $decodedDetails[$detailIndex]['brick_b'] = !empty($detailRow['brick_b']);
                        $decodedDetails[$detailIndex]['duration_seconds'] = max(0, min(7200, (int) ($detailRow['duration_seconds'] ?? 0)));
                        $decodedDetails[$detailIndex]['timer_running'] = !empty($detailRow['timer_running']);
                        $decodedDetails[$detailIndex]['timer_started_at'] = $decodedDetails[$detailIndex]['timer_running'] ? max(0, (int) ($detailRow['timer_started_at'] ?? 0)) : null;
                        $decodedDetails[$detailIndex]['timer_updated_at'] = max(0, (int) ($detailRow['timer_updated_at'] ?? 0));
                        $decodedDetails[$detailIndex]['duel_started'] = !empty($detailRow['duel_started']) || in_array((string) ($detailRow['winner'] ?? ''), ['A', 'B', 'E'], true);
                        $decodedDetails[$detailIndex]['duel_started_at'] = $decodedDetails[$detailIndex]['duel_started'] ? max(0, (int) ($detailRow['duel_started_at'] ?? 0)) : null;
                        $dataOrigin = strtolower(trim((string) ($detailRow['data_origin'] ?? '')));
                        $decodedDetails[$detailIndex]['data_origin'] = in_array($dataOrigin, ['captured', 'added', 'inferred'], true)
                            ? $dataOrigin
                            : ($decodedDetails[$detailIndex]['duel_started_at'] ? 'captured' : 'added');
                        $timerMode = strtolower(trim((string) ($detailRow['timer_mode'] ?? '')));
                        $decodedDetails[$detailIndex]['timer_mode'] = in_array($timerMode, ['none', 'countdown'], true) ? $timerMode : 'none';
                        $turnHistory = is_array($detailRow['turn_history'] ?? null) ? array_slice($detailRow['turn_history'], 0, 999) : [];
                        $decodedDetails[$detailIndex]['turn_history'] = array_values(array_map(static function (array $turn): array {
                            $side = strtoupper((string) ($turn['side'] ?? ''));
                            return [
                                'turn' => max(1, min(999, (int) ($turn['turn'] ?? 1))),
                                'side' => in_array($side, ['A', 'B'], true) ? $side : '',
                                'ended_at' => mb_substr(trim((string) ($turn['ended_at'] ?? '')), 0, 40),
                                'elapsed_seconds' => max(0, min(7200, (int) ($turn['elapsed_seconds'] ?? 0))),
                            ];
                        }, array_filter($turnHistory, 'is_array')));
                        $decodedDetails[$detailIndex]['lp_start_a'] = max(1, min(99999, (int) ($detailRow['lp_start_a'] ?? 8000)));
                        $decodedDetails[$detailIndex]['lp_start_b'] = max(1, min(99999, (int) ($detailRow['lp_start_b'] ?? 8000)));
                        $decodedDetails[$detailIndex]['deck_a_id'] = max(0, (int) ($detailRow['deck_a_id'] ?? 0));
                        $decodedDetails[$detailIndex]['deck_b_id'] = max(0, (int) ($detailRow['deck_b_id'] ?? 0));
                        $decodedDetails[$detailIndex]['player_a_id'] = max(0, (int) ($detailRow['player_a_id'] ?? 0));
                        $decodedDetails[$detailIndex]['player_b_id'] = max(0, (int) ($detailRow['player_b_id'] ?? 0));
                        $historyRows = is_array($detailRow['lp_history'] ?? null) ? array_slice($detailRow['lp_history'], 0, 100) : [];
                        $decodedDetails[$detailIndex]['lp_history'] = array_values(array_map(static function (array $history): array {
                            $side = strtolower((string) ($history['side'] ?? ''));
                            $operation = strtolower((string) ($history['operation'] ?? ''));
                            return [
                                'side' => in_array($side, ['a', 'b'], true) ? $side : '',
                                'operation' => in_array($operation, ['subtract', 'add', 'half', 'reset'], true) ? $operation : '',
                                'amount' => max(0, min(99999, (int) ($history['amount'] ?? 0))),
                                'before' => max(0, min(99999, (int) ($history['before'] ?? 0))),
                                'after' => max(0, min(99999, (int) ($history['after'] ?? 0))),
                            ];
                        }, array_filter($historyRows, 'is_array')));
                    }
                    if ($diceWinner && $turnChosenBy && $turnDecision && $startsPlayer) {
                        $foundD1 = false;
                        foreach ($decodedDetails as &$detailRow) {
                            if ((int) ($detailRow['duel'] ?? 0) === 1) {
                                $detailRow['turn_chosen_by'] = $detailRow['turn_chosen_by'] ?: $turnChosenBy;
                                $detailRow['turn_decision'] = $detailRow['turn_decision'] ?: $turnDecision;
                                $detailRow['starts_player'] = $detailRow['starts_player'] ?: $startsPlayer;
                                $detailRow['turn_reason'] = $detailRow['turn_reason'] ?? 'dice';
                                $foundD1 = true;
                                break;
                            }
                        }
                        unset($detailRow);
                        if (!$foundD1) {
                            array_unshift($decodedDetails, [
                                'duel' => 1,
                                'winner' => $values[0] ?: '',
                                'defeat_method' => '',
                                'lp_a' => '',
                                'lp_b' => '',
                                'turn_chosen_by' => $turnChosenBy,
                                'turn_decision' => $turnDecision,
                                'starts_player' => $startsPlayer,
                                'turn_reason' => 'dice',
                            ]);
                        }
                    }
                    $duelDetails = json_encode(array_values($decodedDetails), JSON_UNESCAPED_UNICODE);
                }
            }
            $manualStarts=(string)($decodedDetails[0]['starts_player']??'');$verifiedManualStart=ygo_match_editor_verified_start($pdo,$matchId,1,$manualStarts);
            if($verifiedManualStart)$startsPlayer=$manualStarts;
            if (array_filter($values) && (!$diceWinner || !$turnChosenBy || !$turnDecision || !$startsPlayer) && !$verifiedManualStart) {
                return 'resultado_bloqueado_inicio';
            }
            if (array_filter($values)) {
                $detailsByDuel = [];
                foreach (($decodedDetails ?? []) as $detailRow) {
                    $detailsByDuel[(int) ($detailRow['duel'] ?? 0)] = $detailRow;
                }
                foreach ($values as $valueIndex => $value) {
                    if ($value === '') {
                        continue;
                    }
                    $detailRow = $detailsByDuel[$valueIndex + 1] ?? [];
                    if (!in_array((string) ($detailRow['starts_player'] ?? ''), ['A', 'B'], true)) {
                        return 'resultado_bloqueado_inicio';
                    }
                    if (empty($detailRow['duel_started'])) {
                        return 'resultado_bloqueado_inicio';
                    }
                    if (($detailRow['defeat_method'] ?? '') === 'surrender') {
                        if (!in_array($value, ['A', 'B'], true)) {
                            return 'resultado_bloqueado_rendicion';
                        }
                        $surrenderingSide = $value === 'A' ? 'B' : 'A';
                        $startsSide = (string) ($detailRow['starts_player'] ?? '');
                        $completedTurns = max((int) ($detailRow['turn_count'] ?? 0), count($detailRow['turn_history'] ?? []));
                        $activeSide = $completedTurns % 2 === 0 ? $startsSide : ($startsSide === 'A' ? 'B' : 'A');
                        $recordedSurrender = (string) ($detailRow['surrendered_by'] ?? '');
                        if ($activeSide !== $surrenderingSide || ($recordedSurrender !== '' && $recordedSurrender !== $surrenderingSide)) {
                            return 'resultado_bloqueado_rendicion';
                        }
                        $decodedDetails[$valueIndex]['surrendered_by'] = $surrenderingSide;
                        $decodedDetails[$valueIndex]['surrendered_at'] = (string) ($detailRow['surrendered_at'] ?? '') ?: date(DATE_ATOM);
                    }
                    if ($valueIndex > 0 && $values[$valueIndex - 1] === 'E') {
                        $hasRecordedDrawReroll = ($detailRow['turn_reason'] ?? '') === 'draw_reroll'
                            && in_array((int) ($detailRow['dice_a'] ?? 0), range(1, 6), true)
                            && in_array((int) ($detailRow['dice_b'] ?? 0), range(1, 6), true)
                            && in_array((string) ($detailRow['dice_winner'] ?? ''), ['A', 'B'], true);
                        $isHistoricalUnrecordedReroll = ($detailRow['turn_reason'] ?? '') === 'draw_reroll_unrecorded'
                            && !empty($detailRow['duel_started'])
                            && !empty($detailRow['turn_history'])
                            && in_array((string) ($detailRow['data_origin'] ?? ''), ['added', 'inferred'], true);
                        if (!$hasRecordedDrawReroll && !$isHistoricalUnrecordedReroll && !ygo_match_editor_verified_start($pdo,$matchId,$valueIndex+1,(string)($detailRow['starts_player']??''))) {
                            return 'resultado_bloqueado_inicio';
                        }
                    }
                }
            }
            $dateGuard = ygo_validate_match_result_date($pdo, $matchId);
            if (!$dateGuard['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', $matchId, 'blocked_result_by_date', json_encode($dateGuard, JSON_UNESCAPED_UNICODE)]);
                return 'resultado_bloqueado_fecha';
            }
            $pdo->beginTransaction();
            ygo_admission_assert_result_editable($pdo,$matchId);
            ygo_workflow_assert_result_editable($pdo,$matchId);
            ygo_season_require_open($pdo, ygo_current_season_id($pdo), true, true);
            $pdo->prepare('INSERT IGNORE INTO sx_ygo_match_results (match_id) VALUES (?)')->execute([$matchId]);
            $existingStmt = $pdo->prepare('SELECT r.d1, r.d2, r.d3, r.d4, r.d5, r.duel_details, m.status AS match_status FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id=r.match_id WHERE r.match_id = ? LIMIT 1 FOR UPDATE');
            $existingStmt->execute([$matchId]);
            $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $existingDetails = json_decode((string) ($existingRow['duel_details'] ?? ''), true);
            $correctionAt=max(array_merge([0],array_map(static fn($d)=>(int)($d['manual_correction_at_ms']??0),is_array($existingDetails)?$existingDetails:[])));
            if($correctionAt>0&&(int)($_POST['result_correction_version']??0)!==$correctionAt)throw new InvalidArgumentException('La partida fue modificada. Recarga antes de guardar para no sobrescribir la corrección.');
            $newTurnAuditRows = [];
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $existingByDuel = [];
                foreach (is_array($existingDetails) ? $existingDetails : [] as $existingDetail) {
                    if (is_array($existingDetail)) {
                        $existingByDuel[(int) ($existingDetail['duel'] ?? 0)] = $existingDetail;
                    }
                }
                $persistedTurnsStmt = $pdo->prepare('
                    SELECT duel_number, turn_number, side, ended_at, elapsed_seconds
                    FROM sx_ygo_turn_audit
                    WHERE match_id = ?
                    ORDER BY duel_number, turn_number
                ');
                $persistedTurnsStmt->execute([$matchId]);
                foreach ($persistedTurnsStmt->fetchAll(PDO::FETCH_ASSOC) as $turnRow) {
                    $duelNumber = (int) $turnRow['duel_number'];
                    if (!isset($existingByDuel[$duelNumber])) {
                        $existingByDuel[$duelNumber] = ['duel' => $duelNumber, 'turn_count' => 0, 'turn_history' => []];
                    }
                    $existingByDuel[$duelNumber]['turn_history'][] = [
                        'turn' => (int) $turnRow['turn_number'],
                        'side' => (string) $turnRow['side'],
                        'ended_at' => (string) ($turnRow['ended_at'] ?? ''),
                        'elapsed_seconds' => (int) $turnRow['elapsed_seconds'],
                    ];
                    $existingByDuel[$duelNumber]['turn_count'] = max(
                        (int) ($existingByDuel[$duelNumber]['turn_count'] ?? 0),
                        (int) $turnRow['turn_number']
                    );
                }
                foreach ($decodedDetails as &$incomingDetail) {
                    $duelNumber = (int) ($incomingDetail['duel'] ?? 0);
                    $storedDetail = $existingByDuel[$duelNumber] ?? [];
                    if(!empty($storedDetail['manual_correction_at_ms']))$incomingDetail['manual_correction_at_ms']=$storedDetail['manual_correction_at_ms'];
                    $mergedTurns = ygo_merge_monotonic_turns($storedDetail, $incomingDetail);
                    $incomingDetail = $mergedTurns['detail'];
                    foreach ($mergedTurns['appended'] as $turnRow) {
                        $newTurnAuditRows[] = ['duel' => $duelNumber, 'row' => $turnRow];
                    }
                    if (!empty($storedDetail['duel_started'])) {
                        $storedTimerMode = strtolower((string) ($storedDetail['timer_mode'] ?? ''));
                        $incomingDetail['duel_started'] = true;
                        $incomingDetail['duel_started_at'] = max(0, (int) ($storedDetail['duel_started_at'] ?? $incomingDetail['duel_started_at'] ?? 0));
                        if (in_array($storedTimerMode, ['none', 'countdown'], true)) {
                            $incomingDetail['timer_mode'] = $storedTimerMode;
                        }
                    }
                    if (($incomingDetail['timer_mode'] ?? 'none') !== 'countdown') { $incomingDetail['timer_running'] = false; $incomingDetail['timer_started_at'] = null; }
                }
                unset($incomingDetail);
                $duelDetails = json_encode(array_values($decodedDetails), JSON_UNESCAPED_UNICODE);
            }
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $turnAuditInsert = $pdo->prepare('
                    INSERT IGNORE INTO sx_ygo_turn_audit
                        (match_id, duel_number, turn_number, side, ended_at, elapsed_seconds)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                foreach ($decodedDetails as $detailRow) {
                    $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
                    foreach (ygo_normalize_turn_history(is_array($detailRow['turn_history'] ?? null) ? $detailRow['turn_history'] : []) as $turnRow) {
                        $turnAuditInsert->execute([
                            $matchId,
                            $duelNumber,
                            (int) $turnRow['turn'],
                            (string) $turnRow['side'],
                            (string) $turnRow['ended_at'] !== '' ? (string) $turnRow['ended_at'] : null,
                            (int) $turnRow['elapsed_seconds'],
                        ]);
                    }
                }
            }
            $existingValues = [];
            for ($i = 1; $i <= 5; $i++) {
                $storedValue = (string) ($existingRow["d$i"] ?? '');
                $existingValues[] = in_array($storedValue, ['A', 'B', 'E'], true) ? $storedValue : '';
            }
            $firstWritable = 0;
            while ($firstWritable < 5 && $existingValues[$firstWritable] !== '') {
                $firstWritable++;
            }
            if ($duelDetails === '') {
                for ($index = 0; $index < 5; $index++) {
                    if ($index < $firstWritable) {
                        $values[$index] = $values[$index] ?: $existingValues[$index];
                        continue;
                    }
                    if ($index > $firstWritable) {
                        $values[$index] = $existingValues[$index] ?: '';
                    }
                }
            }
            $evaluation = ygo_apply_event_model_to_result($pdo, $matchId, ygo_evaluate_duel_with_rule($pdo, $matchId, $values), $values);
            $completionNow = ygo_completion_now();
            $decodedDetails = ygo_completion_games($decodedDetails ?? [], is_array($existingDetails) ? $existingDetails : [], $existingValues, $values, $completionNow, !in_array(strtoupper((string)($existingRow['match_status']??'')), ['FINALIZADA','FINALIZADO','COMPLETED','FINISHED'], true));
            $duelDetails = json_encode($decodedDetails, JSON_UNESCAPED_UNICODE);
            $pdo->prepare("
                INSERT INTO sx_ygo_match_results (match_id, d1, d2, d3, d4, d5, result, score, validation_status, notes, dice_roll, turn_choice, dice_winner, turn_chosen_by, turn_decision, starts_player, duel_details)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    d1 = VALUES(d1), d2 = VALUES(d2), d3 = VALUES(d3), d4 = VALUES(d4), d5 = VALUES(d5),
                    result = VALUES(result), score = VALUES(score), validation_status = VALUES(validation_status), notes = VALUES(notes),
                    dice_roll = VALUES(dice_roll), turn_choice = VALUES(turn_choice), dice_winner = VALUES(dice_winner),
                    turn_chosen_by = VALUES(turn_chosen_by), turn_decision = VALUES(turn_decision), starts_player = VALUES(starts_player),
                    duel_details = VALUES(duel_details)
            ")->execute([
                $matchId,
                $values[0] ?: null,
                $values[1] ?: null,
                $values[2] ?: null,
                $values[3] ?: null,
                $values[4] ?: null,
                $evaluation['winner'],
                $evaluation['score'],
                $evaluation['validation'],
                trim((string) ($_POST['result_notes'] ?? '')),
                $diceRoll !== '' ? $diceRoll : null,
                $turnChoice !== '' ? $turnChoice : null,
                $diceWinner,
                $turnChosenBy,
                $turnDecision,
                $startsPlayer,
                $duelDetails !== '' ? $duelDetails : null,
            ]);
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $matchSnapshotStmt = $pdo->prepare('
                    SELECT m.competition_id, m.division_id, m.deck_a_id, m.deck_b_id,
                           c.event_ruleset, c.ruleset_version, dv.name AS division_name
                    FROM sx_ygo_matches m
                    LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
                    LEFT JOIN sx_ygo_divisions dv ON dv.id = COALESCE(m.division_id, c.division_id)
                    WHERE m.id = ? LIMIT 1
                ');
                $matchSnapshotStmt->execute([$matchId]);
                $matchSnapshot = $matchSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $deckSnapshotStmt = $pdo->prepare('
                    SELECT d.id, d.team_id, d.name, d.archetype, d.variations,
                           t.name AS team_name
                    FROM sx_ygo_decks d
                    LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
                    WHERE d.id = ? LIMIT 1
                ');
                $decklistSnapshotStmt = $pdo->prepare('
                    SELECT card_name, role, quantity, section, notes
                    FROM sx_ygo_deck_cards WHERE deck_id = ? ORDER BY section, id
                ');
                $insertSnapshotStmt = $pdo->prepare('
                    INSERT IGNORE INTO sx_ygo_match_snapshots
                        (match_id, duel_number, side, competition_id, deck_id, team_id, player_id,
                         deck_name, archetype, variation, team_name, ruleset_code, ruleset_version,
                         division_name, decklist_json, decklist_hash, data_origin, snapshot_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                foreach ($decodedDetails as $detailRow) {
                    $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
                    foreach (['A', 'B'] as $side) {
                        $suffix = strtolower($side);
                        $defaultDeckKey = $side === 'A' ? 'deck_a_id' : 'deck_b_id';
                        $deckId = max(0, (int) ($detailRow['deck_' . $suffix . '_id'] ?? $matchSnapshot[$defaultDeckKey] ?? 0));
                        if ($deckId <= 0) {
                            continue;
                        }
                        $deckSnapshotStmt->execute([$deckId]);
                        $deckSnapshot = $deckSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        $decklistSnapshotStmt->execute([$deckId]);
                        $decklist = $decklistSnapshotStmt->fetchAll(PDO::FETCH_ASSOC);
                        $originValue = strtolower(trim((string) ($detailRow['data_origin'] ?? $detailRow['source'] ?? '')));
                        $hasTurnHistory = !empty($detailRow['turn_history']) && is_array($detailRow['turn_history']);
                        $origin = str_contains($originValue, 'infer') || str_contains($originValue, 'backfill')
                            ? 'inferred'
                            : (str_contains($originValue, 'add') ? 'added' : ($originValue === 'captured' || $hasTurnHistory ? 'captured' : 'unknown'));
                        $decklistJson = json_encode($decklist, JSON_UNESCAPED_UNICODE);
                        $insertSnapshotStmt->execute([
                            $matchId, $duelNumber, $side,
                            (int) ($matchSnapshot['competition_id'] ?? 0) ?: null,
                            $deckId,
                            (int) ($deckSnapshot['team_id'] ?? 0) ?: null,
                            max(0, (int) ($detailRow['player_' . $suffix . '_id'] ?? 0)) ?: null,
                            (string) ($deckSnapshot['name'] ?? ''),
                            (string) ($deckSnapshot['archetype'] ?? ''),
                            (string) ($deckSnapshot['variations'] ?? ''),
                            (string) ($deckSnapshot['team_name'] ?? ''),
                            (string) ($matchSnapshot['event_ruleset'] ?? ''),
                            (string) ($matchSnapshot['ruleset_version'] ?? ''),
                            (string) ($matchSnapshot['division_name'] ?? ''),
                            $decklistJson,
                            hash('sha256', $decklistJson ?: '[]'),
                            $origin,
                            $origin === 'captured' ? 'exact' : $origin,
                        ]);
                    }
                }
            }
            ygo_completion_set_status($pdo, $matchId, $evaluation['status'], $completionNow);
            $competitionStmt = $pdo->prepare('SELECT competition_id FROM sx_ygo_matches WHERE id = ? LIMIT 1');
            $competitionStmt->execute([$matchId]);
            $resultCompetitionId = (int) ($competitionStmt->fetchColumn() ?: 0);
            if ($resultCompetitionId > 0 && !in_array('ranking_impact:NONE', $evaluation['model_effects'] ?? [], true)) {
                ygo_persist_competition_standings($pdo, ygo_current_season_id($pdo), $resultCompetitionId, 'save_match_result');
            }
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'save_result', json_encode(['duels' => $values, 'evaluation' => $evaluation, 'date_guard' => $dateGuard, 'dice_roll' => $diceRoll, 'turn_choice' => $turnChoice, 'dice_winner' => $diceWinner, 'turn_chosen_by' => $turnChosenBy, 'turn_decision' => $turnDecision, 'starts_player' => $startsPlayer, 'duel_details' => $duelDetails], JSON_UNESCAPED_UNICODE)]);
            $turnAuditLog = $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)');
            foreach ($newTurnAuditRows as $auditRow) {
                $turnAuditLog->execute([
                    'match',
                    $matchId,
                    'turn_added',
                    json_encode([
                        'duel' => (int) $auditRow['duel'],
                        'turn' => (int) $auditRow['row']['turn'],
                        'side' => (string) $auditRow['row']['side'],
                        'ended_at' => (string) $auditRow['row']['ended_at'],
                        'elapsed_seconds' => (int) $auditRow['row']['elapsed_seconds'],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
            ygo_sync_meta_history($pdo);
            ygo_sync_elo_history($pdo);
            ygo_sync_global_ranking_history($pdo);
            ygo_sync_event_summaries($pdo, $resultCompetitionId ?: null);
            ygo_sync_medal_unlocks($pdo, $matchId);
            $savedLiveMatch = ygo_live_duel_match($pdo, $matchId);
            if ($savedLiveMatch) {
                $savedLiveState = ygo_live_duel_initial_state($savedLiveMatch);
                if (($evaluation['status'] ?? '') === 'FINALIZADA') {
                    $savedLiveState['status'] = 'completed';
                    $savedLiveState['active_side'] = '';
                }
                $pdo->prepare("
                    INSERT INTO sx_ygo_live_duel_state (match_id, duel_number, revision, state_json, updated_by)
                    VALUES (?, ?, 1, ?, 'judge')
                    ON DUPLICATE KEY UPDATE duel_number=VALUES(duel_number), revision=revision+1, state_json=VALUES(state_json), updated_by='judge'
                ")->execute([$matchId, $savedLiveState['duel_number'], json_encode($savedLiveState, JSON_UNESCAPED_UNICODE)]);
            }
            if ($offlineRequestId !== '') {
                $pdo->prepare('INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)')
                    ->execute([$offlineRequestId,$matchId,hash('sha256',json_encode($_POST,JSON_UNESCAPED_UNICODE) ?: ''),'resultado']);
            }
            $pdo->commit();
        }
        return 'resultado';
    }
    if ($action === 'save_deck_analysis_profile') {
        $deckId = max(0, (int) ($_POST['deck_analysis_deck_id'] ?? 0));
        $deckStmt = $pdo->prepare('SELECT * FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
        $deckStmt->execute([$deckId]);
        $deck = $deckStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$deck) throw new RuntimeException('El deck seleccionado ya no esta activo.');
        $profileListJson = static function (string $field): string {
            $raw = (string) ($_POST[$field] ?? '');
            $structured = json_decode($raw, true);
            $isStructured = is_array($structured) && array_is_list($structured) && count(array_filter($structured, 'is_string')) === count($structured);
            $rows = $isStructured ? $structured : (preg_split('/[\r\n,;]+/', $raw) ?: []);
            $limit = $isStructured ? 2400 : 240;
            $rows = array_values(array_unique(array_filter(array_map(static fn($value): string => mb_substr(trim((string) $value), 0, $limit), $rows))));
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $score = static fn(string $field): int => max(0, min(5, (int) ($_POST[$field] ?? 0)));
        $confidence = strtoupper(trim((string) ($_POST['deck_analysis_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) $confidence = 'MEDIA';
        $sourceType = strtoupper(trim((string) ($_POST['deck_analysis_source_type'] ?? 'EDITORIAL')));
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) $sourceType = 'EDITORIAL';
        $values = [
            mb_substr(trim((string) ($_POST['deck_analysis_play_style'] ?? '')), 0, 120),
            mb_substr(trim((string) ($_POST['deck_analysis_pace'] ?? '')), 0, 30),
            mb_substr(trim((string) ($_POST['deck_analysis_complexity'] ?? '')), 0, 30),
            trim((string) ($_POST['deck_analysis_primary_game_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_win_condition'] ?? '')),
            trim((string) ($_POST['deck_analysis_resource_engine'] ?? '')),
            trim((string) ($_POST['deck_analysis_opening_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_mid_game_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_late_game_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_going_first_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_going_second_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_side_deck_strategy'] ?? '')),
            $profileListJson('deck_analysis_strengths'),
            $profileListJson('deck_analysis_weaknesses'),
            $profileListJson('deck_analysis_strategy_tags'),
            $score('deck_analysis_consistency_score'),
            $score('deck_analysis_ceiling_score'),
            $score('deck_analysis_resilience_score'),
            $score('deck_analysis_interaction_score'),
            $score('deck_analysis_recovery_score'),
            $score('deck_analysis_first_score'),
            $score('deck_analysis_second_score'),
            $confidence,
            $sourceType,
            trim((string) ($_POST['deck_analysis_notes'] ?? '')),
        ];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO sx_ygo_deck_analysis_profiles
                    (deck_id, play_style, pace, complexity, primary_game_plan, win_condition, resource_engine, opening_plan, mid_game_plan, late_game_plan, going_first_plan, going_second_plan, side_deck_strategy, strengths_json, weaknesses_json, strategy_tags_json, consistency_score, ceiling_score, resilience_score, interaction_score, recovery_score, first_score, second_score, confidence, source_type, analysis_notes, revision)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE play_style=VALUES(play_style), pace=VALUES(pace), complexity=VALUES(complexity), primary_game_plan=VALUES(primary_game_plan), win_condition=VALUES(win_condition), resource_engine=VALUES(resource_engine), opening_plan=VALUES(opening_plan), mid_game_plan=VALUES(mid_game_plan), late_game_plan=VALUES(late_game_plan), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_deck_strategy=VALUES(side_deck_strategy), strengths_json=VALUES(strengths_json), weaknesses_json=VALUES(weaknesses_json), strategy_tags_json=VALUES(strategy_tags_json), consistency_score=VALUES(consistency_score), ceiling_score=VALUES(ceiling_score), resilience_score=VALUES(resilience_score), interaction_score=VALUES(interaction_score), recovery_score=VALUES(recovery_score), first_score=VALUES(first_score), second_score=VALUES(second_score), confidence=VALUES(confidence), source_type=VALUES(source_type), analysis_notes=VALUES(analysis_notes), revision=revision+1
            ")->execute([$deckId, ...$values]);
            $savedStmt = $pdo->prepare('SELECT * FROM sx_ygo_deck_analysis_profiles WHERE deck_id=? LIMIT 1');
            $savedStmt->execute([$deckId]);
            $saved = $savedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $revision = (int) ($saved['revision'] ?? 1);
            $pdo->prepare('INSERT INTO sx_ygo_deck_analysis_profile_history (deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?)')
                ->execute([$deckId, $revision, json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), trim((string) ($_POST['deck_analysis_change_reason'] ?? ''))]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['deck_analysis', $deckId, 'save_deck_analysis_profile', json_encode(['revision' => $revision, 'source_type' => $sourceType], JSON_UNESCAPED_UNICODE)]);
            if ($startedTransaction) $pdo->commit();
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'deck_analysis_' . $deckId;
    }
    if ($action === 'save_matchup_analysis') {
        $deckId = max(0, (int) ($_POST['matchup_deck_id'] ?? 0));
        $opponentId = max(0, (int) ($_POST['matchup_opponent_deck_id'] ?? 0));
        if ($deckId <= 0 || $opponentId <= 0 || $deckId === $opponentId) {
            throw new RuntimeException('Selecciona dos decks diferentes para guardar el analisis.');
        }
        $validDeck = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_decks WHERE id IN (?, ?) AND active=1');
        $validDeck->execute([$deckId, $opponentId]);
        if ((int) $validDeck->fetchColumn() !== 2) {
            throw new RuntimeException('Uno de los decks del matchup ya no esta activo.');
        }
        $score = max(-2, min(2, (int) ($_POST['strategic_score'] ?? 0)));
        $classificationByScore = [-2 => 'MUY_DESFAVORABLE', -1 => 'DESFAVORABLE', 0 => 'EQUILIBRADO', 1 => 'FAVORABLE', 2 => 'MUY_FAVORABLE'];
        $confidence = strtoupper(trim((string) ($_POST['matchup_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) $confidence = 'MEDIA';
        $sourceType = strtoupper(trim((string) ($_POST['matchup_source_type'] ?? 'EDITORIAL')));
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) $sourceType = 'EDITORIAL';
        $listJson = static function (string $field): string {
            $rows = preg_split('/[\r\n,;]+/', (string) ($_POST[$field] ?? '')) ?: [];
            $rows = array_values(array_unique(array_filter(array_map(static fn($value): string => mb_substr(trim((string) $value), 0, 240), $rows))));
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $scope = [
            mb_substr(trim((string) ($_POST['matchup_deck_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($_POST['matchup_opponent_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($_POST['matchup_ruleset_version'] ?? '')), 0, 180),
        ];
        $values = [
            $score,
            $classificationByScore[$score],
            trim((string) ($_POST['matchup_reason'] ?? '')),
            $listJson('matchup_advantages'),
            $listJson('matchup_disadvantages'),
            $listJson('matchup_choke_points'),
            trim((string) ($_POST['matchup_going_first_plan'] ?? '')),
            trim((string) ($_POST['matchup_going_second_plan'] ?? '')),
            trim((string) ($_POST['matchup_side_plan'] ?? '')),
            $confidence,
            $sourceType,
            trim((string) ($_POST['matchup_review_notes'] ?? '')),
        ];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO sx_ygo_matchup_analyses
                    (deck_id, opponent_deck_id, deck_version, opponent_version, ruleset_version, strategic_score, classification, reason, key_advantages_json, key_disadvantages_json, choke_points_json, going_first_plan, going_second_plan, side_plan, confidence, source_type, review_notes, revision, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                ON DUPLICATE KEY UPDATE strategic_score=VALUES(strategic_score), classification=VALUES(classification), reason=VALUES(reason), key_advantages_json=VALUES(key_advantages_json), key_disadvantages_json=VALUES(key_disadvantages_json), choke_points_json=VALUES(choke_points_json), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_plan=VALUES(side_plan), confidence=VALUES(confidence), source_type=VALUES(source_type), review_notes=VALUES(review_notes), revision=revision+1, active=1
            ")->execute([$deckId, $opponentId, ...$scope, ...$values]);
            $analysisStmt = $pdo->prepare('SELECT * FROM sx_ygo_matchup_analyses WHERE deck_id=? AND opponent_deck_id=? AND deck_version=? AND opponent_version=? AND ruleset_version=? LIMIT 1');
            $analysisStmt->execute([$deckId, $opponentId, ...$scope]);
            $analysis = $analysisStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $analysisId = (int) ($analysis['id'] ?? 0);
            $revision = (int) ($analysis['revision'] ?? 1);
            $pdo->prepare('INSERT INTO sx_ygo_matchup_analysis_history (analysis_id, deck_id, opponent_deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$analysisId, $deckId, $opponentId, $revision, json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), trim((string) ($_POST['matchup_change_reason'] ?? ''))]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['matchup_analysis', $analysisId, 'save_matchup_analysis', json_encode(['deck_id' => $deckId, 'opponent_deck_id' => $opponentId, 'revision' => $revision, 'source_type' => $sourceType], JSON_UNESCAPED_UNICODE)]);
            if ($startedTransaction) $pdo->commit();
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'matchup_deck_' . $deckId;
    }
    if ($action === 'save_deck') {
        $id = (int) ($_POST['deck_id'] ?? 0);
        $deckName = trim((string) $_POST['deck_name']);
        $archetype = trim((string) $_POST['archetype']);
        $existingTeamId = null;
        if ($id > 0) {
            $existingTeamStmt = $pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
            $existingTeamStmt->execute([$id]);
            $existingTeamId = $existingTeamStmt->fetchColumn();
            $existingTeamId = $existingTeamId === false || $existingTeamId === null ? null : (int) $existingTeamId;
        }
        $existingCoverUrl = '';
        if ($id > 0) {
            $existingCoverStmt = $pdo->prepare('SELECT cover_url FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
            $existingCoverStmt->execute([$id]);
            $existingCoverUrl = (string) ($existingCoverStmt->fetchColumn() ?: '');
        }
        $coverUrl = trim((string) ($_POST['cover_url'] ?? '')) ?: $existingCoverUrl;
        $teamId = array_key_exists('team_id', $_POST) ? (($_POST['team_id'] ?? '') === '' ? 0 : (int) $_POST['team_id']) : (int) ($existingTeamId ?? 0);
        if ($id <= 0 && $teamId <= 0 && $deckName !== '' && array_key_exists('team_id', $_POST) && ($_POST['team_id'] ?? '') !== '') {
            $profile = ygo_team_profile_for_deck($deckName, $archetype);
            $teamId = ygo_find_or_create_team($pdo, $profile['name'], $profile);
        }
        $values = [
            ($_POST['division_id'] ?? '') === '' ? null : (int) $_POST['division_id'],
            $teamId > 0 ? $teamId : null,
            max(1, (int) ($_POST['seed_rank'] ?? 999)),
            $deckName,
            $archetype,
            trim((string) $_POST['style']),
            trim((string) $_POST['reason']),
            trim((string) $_POST['key_cards']),
            trim((string) $_POST['variations']),
            trim((string) $_POST['notes']),
            $coverUrl,
        ];
        if ($values[3] !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_decks SET division_id=?, team_id=?, seed_rank=?, name=?, archetype=?, style=?, reason=?, key_cards=?, variations=?, notes=?, cover_url=?, active=1 WHERE id=?')->execute([...$values, $id]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')->execute($values);
                $id = (int) $pdo->lastInsertId();
            }
            $uploadedDeckLogo = ygo_store_deck_logo_upload($id);
            if ($uploadedDeckLogo !== '') {
                $pdo->prepare('UPDATE sx_ygo_decks SET cover_url=? WHERE id=?')->execute([$uploadedDeckLogo, $id]);
                ygo_record_logo_history($pdo, 'deck', $id, $uploadedDeckLogo);
            }
        }
        return 'deck_' . $id;
    }
    if ($action === 'remove_deck') {
        $id = (int) ($_POST['deck_id'] ?? 0);
        if ($id > 0) {
            $blocker = ygo_deck_delete_blocker($pdo, $id);
            if ($blocker) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['deck', $id, 'blocked_remove_deck_in_event', json_encode($blocker, JSON_UNESCAPED_UNICODE)]);
                return 'deck_bloqueado_evento';
            }
            $pdo->prepare('UPDATE sx_ygo_decks SET active=0 WHERE id=?')->execute([$id]);
        }
        return 'deck_quitado';
    }
    if ($action === 'save_competition') {
        $seasonId = ygo_current_season_id($pdo);
        $id = (int) ($_POST['competition_id'] ?? 0);
        $competitionName = trim((string) $_POST['competition_name']);
        $competitionType = trim((string) $_POST['competition_type']);
        $postedDivision = trim((string) ($_POST['competition_division_id'] ?? ''));
        $postedDivisionIds = $_POST['competition_division_ids'] ?? [];
        if (!is_array($postedDivisionIds)) {
            $postedDivisionIds = [];
        }
        $postedDivisionIds = array_values(array_unique(array_filter(array_map('intval', $postedDivisionIds), static fn(int $divisionId): bool => $divisionId > 0)));
        $competitionRuleset = trim((string) ($_POST['competition_event_ruleset'] ?? 'tcg_advanced'));
        if (str_starts_with($postedDivision, 'format:')) {
            $competitionRuleset = substr($postedDivision, 7) ?: $competitionRuleset;
        }
        $rulesetStmt = $pdo->prepare('SELECT * FROM sx_ygo_rulesets WHERE season_id = ? AND code = ? AND active = 1 LIMIT 1');
        $rulesetStmt->execute([$seasonId, $competitionRuleset]);
        $selectedRuleset = $rulesetStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $existingCompetition = [];
        if ($id > 0) {
            $existingStmt = $pdo->prepare("
                SELECT c.*, r.code AS game_rule_code
                FROM sx_ygo_competitions c
                LEFT JOIN sx_ygo_game_rules r ON r.id = c.game_rule_id
                WHERE c.id = ? AND c.season_id = ?
                LIMIT 1
            ");
            $existingStmt->execute([$id, $seasonId]);
            $existingCompetition = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($existingCompetition['type'])) {
                $competitionType = (string) $existingCompetition['type'];
            }
        }
        if ($id>0 && !empty($existingCompetition['configuration_locked'])) throw new InvalidArgumentException('El evento esta bloqueado. Usa su ficha sin reescribir la definicion ni el calendario.');
        if($id>0)ygo_assert_fixture_mutable($pdo,$id);
        $competitionCode = $id > 0 ? trim((string) ($existingCompetition['code'] ?? '')) : '';
        if ($competitionCode === '' && $competitionName !== '') {
            $competitionCode = ygo_generate_competition_code($pdo, $seasonId, $competitionType, $competitionRuleset, $competitionName, $id);
        }
        $matchFormat = trim((string) ($_POST['competition_match_format'] ?? ($selectedRuleset['match_format'] ?? '1v1')));
        if (!in_array($matchFormat, ['1v1', '2v2', '3v3', 'ffa'], true)) {
            $matchFormat = '1v1';
        }
        $participantsPerSide = $matchFormat==='ffa'?1:(int) substr($matchFormat, 0, 1);
        $postedDivisionId = $postedDivision !== '' && ctype_digit($postedDivision) && (int) $postedDivision > 0 ? (int) $postedDivision : null;
        if ($postedDivisionId && !in_array($postedDivisionId, $postedDivisionIds, true)) {
            array_unshift($postedDivisionIds, $postedDivisionId);
        }
        if ($competitionType === 'league' && !$postedDivisionIds && $id > 0) {
            $linkedDivisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND league_competition_id=? ORDER BY tier_order,id');
            $linkedDivisionStmt->execute([$seasonId, $id]);
            $postedDivisionIds = array_map('intval', $linkedDivisionStmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($competitionType === 'league' && !$postedDivisionIds && !empty($existingCompetition['division_id'])) {
            $postedDivisionIds = [(int) $existingCompetition['division_id']];
        }
        if ($competitionType === 'league' && $postedDivisionIds) {
            $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
            $divisionStmt = $pdo->prepare("SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND id IN ($placeholders) ORDER BY FIELD(id,$placeholders)");
            $divisionStmt->execute(array_merge([$seasonId], $postedDivisionIds, $postedDivisionIds));
            $postedDivisionIds = array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN));
            $postedDivisionId = $postedDivisionId && in_array($postedDivisionId, $postedDivisionIds, true) ? $postedDivisionId : ($postedDivisionIds[0] ?? null);
        }
        if ($competitionType === 'league' && !$postedDivisionId) {
            throw new RuntimeException('Toda liga debe tener una division activa asociada.');
        }
        $deckPoolMode = (string) ($_POST['competition_deck_pool'] ?? 'all_active');
        if (!in_array($deckPoolMode, ['all_active', 'division'], true)) {
            $deckPoolMode = 'all_active';
        }
        if ($deckPoolMode === 'division' && (!$postedDivisionId || $postedDivisionId <= 0)) {
            $deckPoolMode = 'all_active';
        }
        $hasLeagueMovements = $competitionType === 'league' && count($postedDivisionIds) > 1;
        $leaguePromotionSlots = $hasLeagueMovements ? max(0, (int) ($_POST['competition_promotion_slots'] ?? 0)) : 0;
        $leagueRelegationSlots = $hasLeagueMovements ? max(0, (int) ($_POST['competition_relegation_slots'] ?? 0)) : 0;
        $leaguePromotionTo = $hasLeagueMovements && ($_POST['competition_promotion_to'] ?? '') !== '' ? (int) $_POST['competition_promotion_to'] : null;
        $leagueRelegationTo = $hasLeagueMovements && ($_POST['competition_relegation_to'] ?? '') !== '' ? (int) $_POST['competition_relegation_to'] : null;
        if ($postedDivisionId && $leaguePromotionTo === $postedDivisionId) {
            $leaguePromotionTo = null;
        }
        if ($postedDivisionId && $leagueRelegationTo === $postedDivisionId) {
            $leagueRelegationTo = null;
        }
        $lockDivisionMoves = $hasLeagueMovements && isset($_POST['competition_lock_division_moves']) ? 1 : 0;
        $qualifierDuelsPerDeck = $competitionType === 'qualifier' ? max(2, min(5, (int) ($_POST['qualifier_duels_per_deck'] ?? 3))) : 3;
        $qualifierMatchesPerDay = $competitionType === 'qualifier' ? max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? 1))) : 1;
        $rulesetVersion = trim((string) ($_POST['competition_ruleset_version'] ?? ''));
        if($id===0 && $competitionRuleset!=='goat' && preg_match('/(?:abril|april)\s*2005|goat/i',$rulesetVersion)) throw new InvalidArgumentException('La version GOAT/Abril 2005 no corresponde al formato elegido. Selecciona su reglamento.');
        $promptTemplate = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['competition_prompt_template'] ?? '')));
        $eventSystem = trim((string) ($_POST['competition_event_system'] ?? ''));
        $executionModel = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['competition_execution_model'] ?? '')));
        $templateType = $promptTemplate !== '' ? ygo_event_template_type($promptTemplate) : '';
        if ($templateType !== '' && $templateType !== $competitionType) throw new InvalidArgumentException('El tipo de evento no corresponde a la plantilla elegida; no se borrara ni sustituira silenciosamente.');
        ygo_require_execution_type($competitionType, $executionModel);
        $participation = $matchFormat === 'ffa'?'FREE_FOR_ALL':($matchFormat === '3v3' ? 'TEAM_3V3' : ($matchFormat === '2v2' ? 'TAG_2V2' : 'INDIVIDUAL_1V1'));
        $construction = trim((string) ($_POST['competition_construction'] ?? ''));
        $environment = trim((string) ($_POST['competition_environment'] ?? ''));
        $level = trim((string) ($_POST['competition_level'] ?? ''));
        $eventFlow = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_event_flow'] ?? 'STANDALONE'))));
        $participantSource = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_participant_source'] ?? 'MANUAL'))));
        $accessType = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_access_type'] ?? 'OPEN'))));
        $advancementRule = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_advancement_rule'] ?? 'MANUAL_SELECTION'))));
        $drawPolicy = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_draw_policy'] ?? 'USE_TIEBREAKER'))));
        $rankingImpact = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_ranking_impact'] ?? 'FULL'))));
        $deckPolicy = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_deck_policy'] ?? 'OPTIONAL_REGISTRATION'))));
        $phaseStructure = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_phase_structure'] ?? 'NONE'))));
        $choiceErrors=ygo_event_choice_errors(['participation'=>$participation,'construction'=>$construction,'environment'=>$environment,'level'=>$level,'event_flow'=>$eventFlow,'participant_source'=>$participantSource,'access_type'=>$accessType,'advancement_rule'=>$advancementRule,'draw_policy'=>$drawPolicy,'ranking_impact'=>$rankingImpact,'deck_policy'=>$deckPolicy,'phase_structure'=>$phaseStructure]);
        if($choiceErrors)throw new InvalidArgumentException(implode(' ',$choiceErrors));
        if($promptTemplate!=='' && !in_array($promptTemplate,array_column(ygo_event_available_templates(),'code'),true))throw new InvalidArgumentException('Modelo preconfigurado desconocido.');
        if ($competitionType === 'friendly') {
            $rankingImpact = 'NONE';
            $level = $level ?: 'CASUAL';
        }
        $uploadedLogo = ygo_store_event_logo_upload();
        $galleryLogo = ygo_valid_event_gallery_logo((string) ($_POST['competition_gallery_logo'] ?? ''), $competitionType);
        $eventTypeAccent = (string) (($GLOBALS['competitionTypeOptions'][$competitionType]['accent'] ?? '') ?: '#36d8ff');
        $defaultLogos = ygo_event_logo_gallery()[$competitionType] ?? [];
        $defaultLogo = (string) ($defaultLogos[0]['url'] ?? '');
        $logoUrl = $uploadedLogo !== '' ? $uploadedLogo : ($galleryLogo !== '' ? $galleryLogo : ((string) ($existingCompetition['logo_url'] ?? '') ?: $defaultLogo));
        $postedCreateStatus = trim((string) ($_POST['competition_status'] ?? 'pending_configuration'));
        $competitionStatus = $postedCreateStatus === 'cancelled' && $id > 0 ? 'cancelled' : 'pending_configuration';
        $existingRuleCode = (string) ($existingCompetition['game_rule_code'] ?? '');
        $fallbackRuleId = (int) ($existingCompetition['game_rule_id'] ?? 0) ?: null;
        $gameRuleId = ygo_valid_game_rule_id($pdo, $seasonId, (int) ($_POST['competition_game_rule_id'] ?? 0), $fallbackRuleId, $competitionType, $executionModel);
        $bestOf=filter_var($_POST['competition_best_of'] ?? $selectedRuleset['best_of'] ?? 3,FILTER_VALIDATE_INT);
        if($bestOf===false || $bestOf<1 || $bestOf>5)throw new InvalidArgumentException('Mejor de debe ser un entero entre 1 y 5; no se permite D6.');
        if ($bestOf===1 && empty($_POST['competition_game_rule_id'])) $gameRuleId=null;
        if (in_array($promptTemplate,['team_league','team_cup','team_championship'],true) && !in_array($matchFormat,['2v2','3v3'],true)) {
            throw new InvalidArgumentException('Selecciona expresamente 2v2 o 3v3 para este modelo de equipos.');
        }
        $operationalParameters=[];
        foreach(['swiss_rounds','top_n','group_max_size','group_qualifiers','series_matches'] as $parameter){
            $posted=trim((string)($_POST['competition_parameter_'.$parameter]??''));if($posted==='')continue;
            $value=filter_var($posted,FILTER_VALIDATE_INT);if($value===false||$value<1||$value>64)throw new InvalidArgumentException('Parametro operativo invalido: '.$parameter);
            $operationalParameters[$parameter]=$value;
        }
        if(isset($operationalParameters['series_matches'])){if($operationalParameters['series_matches']%2!==1)throw new InvalidArgumentException('La serie debe tener un numero impar de matches.');$operationalParameters['series_wins']=intdiv($operationalParameters['series_matches'],2)+1;}
        foreach(array_keys($operationalParameters) as $key)if(!in_array($key,ygo_execution_parameter_fields($executionModel),true))throw new InvalidArgumentException('El parametro no se aplica al sistema elegido: '.$key);
        if($promptTemplate==='clasificatorio_divisiones_3_duelos'&&(($operationalParameters['swiss_rounds']??3)>3||$qualifierDuelsPerDeck>3))throw new InvalidArgumentException('Esta plantilla admite hasta tres rondas/enfrentamientos por deck; elige Suizo personalizado para otro limite.');
        if(isset($operationalParameters['group_max_size'])&&$operationalParameters['group_max_size']<2)throw new InvalidArgumentException('Un grupo competitivo requiere al menos dos participantes.');
        if($executionModel==='groups_knockout'&&($operationalParameters['group_qualifiers']??2)>($operationalParameters['group_max_size']??4))throw new InvalidArgumentException('Los cupos por grupo superan su capacidad.');
        $preset=null;foreach(ygo_event_available_templates() as $t)if($t['code']===$promptTemplate)$preset=$t;
        if($executionModel==='free_for_all'){$rankingImpact='NONE';$gameRuleId=null;if($matchFormat!=='ffa'||$bestOf!==1)throw new InvalidArgumentException('Battle Royale requiere participacion multijugador y un duelo.');}
        $contractErrors=ygo_event_contract_errors(['type'=>$competitionType,'prompt_template'=>$promptTemplate,'execution_model'=>$executionModel,'phase_structure'=>$phaseStructure,'advancement_rule'=>$advancementRule,'best_of'=>$bestOf,'participant_source'=>$participantSource,'match_format'=>$matchFormat,'event_ruleset'=>$competitionRuleset],$preset);
        if($contractErrors)throw new InvalidArgumentException(implode(' ',$contractErrors));
        $values = [
            $seasonId,
            $postedDivisionId,
            $competitionCode,
            $competitionName,
            $competitionType,
            trim((string) ($_POST['competition_scope'] ?? 'division')),
            trim((string) ($_POST['competition_format'] ?? 'editable')),
            $matchFormat,
            $bestOf,
            max(1, min(3, $participantsPerSide)),
            trim((string) ($selectedRuleset['schedule_strategy'] ?? ($_POST['competition_schedule_strategy'] ?? 'manual'))),
            $competitionRuleset,
            $rulesetVersion,
            $deckPoolMode,
            $leaguePromotionSlots,
            $leagueRelegationSlots,
            $leaguePromotionTo,
            $leagueRelegationTo,
            $lockDivisionMoves,
            trim((string) ($selectedRuleset['victory_condition'] ?? 'Resultado definido por reglamento')),
            trim((string) ($selectedRuleset['defeat_condition'] ?? 'Resultado definido por reglamento')),
            trim((string) ($selectedRuleset['draw_condition'] ?? 'Empate definido por reglamento')),
            $promptTemplate !== '' ? $promptTemplate : null,
            $eventSystem !== '' ? $eventSystem : null,
            $executionModel !== '' ? $executionModel : null,
            $participation !== '' ? $participation : null,
            $construction !== '' ? $construction : null,
            $environment !== '' ? $environment : null,
            $level !== '' ? $level : null,
            $eventFlow !== '' ? $eventFlow : 'STANDALONE',
            $participantSource !== '' ? $participantSource : 'MANUAL',
            $advancementRule !== '' ? $advancementRule : 'MANUAL_SELECTION',
            $drawPolicy !== '' ? $drawPolicy : 'USE_TIEBREAKER',
            $rankingImpact !== '' ? $rankingImpact : 'FULL',
            $deckPolicy !== '' ? $deckPolicy : 'OPTIONAL_REGISTRATION',
            $accessType !== '' ? $accessType : 'OPEN',
            $phaseStructure !== '' ? $phaseStructure : 'NONE',
            $qualifierDuelsPerDeck,
            $qualifierMatchesPerDay,
            $gameRuleId,
            $competitionStatus,
            ($_POST['starts_on'] ?? '') ?: null,
            ($_POST['ends_on'] ?? '') ?: null,
            $eventTypeAccent,
            $logoUrl,
            trim((string) $_POST['competition_rules']),
        ];
        if ($values[2] !== '' && $values[3] !== '') {
            $targetCompetitionId = $id;
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_competitions SET division_id=?, code=?, name=?, type=?, scope=?, format=?, match_format=?, best_of=?, participants_per_side=?, schedule_strategy=?, event_ruleset=?, ruleset_version=?, deck_pool=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, lock_division_moves=?, victory_condition=?, defeat_condition=?, draw_condition=?, prompt_template=?, event_system=?, execution_model=?, participation=?, construction=?, environment=?, level=?, event_flow=?, participant_source=?, advancement_rule=?, draw_policy=?, ranking_impact=?, deck_policy=?, access_type=?, phase_structure=?, qualifier_duels_per_deck=?, qualifier_matches_per_day=?, game_rule_id=?, status=?, starts_on=?, ends_on=?, accent=?, logo_url=?, rules=?, active=1 WHERE id=?')->execute(array_merge(array_slice($values, 1), [$id]));
                ygo_record_competition_status_history($pdo, $seasonId, $id, (string) ($existingCompetition['status'] ?? ''), $competitionStatus, 'save_competition', ['source' => 'create_event_screen']);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_competitions (season_id, division_id, code, name, type, scope, format, match_format, best_of, participants_per_side, schedule_strategy, event_ruleset, ruleset_version, deck_pool, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, lock_division_moves, victory_condition, defeat_condition, draw_condition, prompt_template, event_system, execution_model, participation, construction, environment, level, event_flow, participant_source, advancement_rule, draw_policy, ranking_impact, deck_policy, access_type, phase_structure, qualifier_duels_per_deck, qualifier_matches_per_day, game_rule_id, status, starts_on, ends_on, accent, logo_url, rules, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')->execute($values);
                $targetCompetitionId = (int) $pdo->lastInsertId();
                ygo_record_competition_status_history($pdo, $seasonId, $targetCompetitionId, null, $competitionStatus, 'save_competition', ['source' => 'create_event_screen']);
            }
            if ($targetCompetitionId > 0) {
                if ($competitionType === 'league' && $postedDivisionIds) {
                    $pdo->prepare('UPDATE sx_ygo_divisions SET league_competition_id=NULL WHERE season_id=? AND league_competition_id=?')
                        ->execute([$seasonId, $targetCompetitionId]);
                    $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
                    $pdo->prepare("UPDATE sx_ygo_divisions SET league_competition_id=? WHERE season_id=? AND active=1 AND id IN ($placeholders)")
                        ->execute(array_merge([$targetCompetitionId, $seasonId], $postedDivisionIds));
                }
                $sameRecipe=($existingCompetition['prompt_template']??null)===$promptTemplate && ($existingCompetition['execution_model']??null)===$executionModel;
                $pdo->prepare('UPDATE sx_ygo_competitions SET execution_parameters=? WHERE id=?')->execute([$operationalParameters?json_encode($operationalParameters):($sameRecipe?($existingCompetition['execution_parameters']??null):null),$targetCompetitionId]);
                ygo_record_logo_history($pdo, 'competition', $targetCompetitionId, $logoUrl, $uploadedLogo !== '' ? 'upload' : ($galleryLogo !== '' ? 'gallery' : 'default'));
                ygo_persist_competition_calendar_event($pdo, $seasonId, $targetCompetitionId);
            }
            $conditionQualifierId = (int) ($_POST['condition_qualifier_id'] ?? 0);
            if ($targetCompetitionId > 0 && in_array($competitionType, ['league', 'tournament', 'world'], true)) {
                $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')->execute([$targetCompetitionId]);
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
                        ')->execute([$seasonId, $targetCompetitionId, $validQualifierId]);
                    }
                }
            }
        }
        return $targetCompetitionId > 0 ? 'evento_configurado_' . $targetCompetitionId : 'evento';
    }
    if ($action === 'save_ruleset') {
        $seasonId = ygo_current_season_id($pdo);
        $code = trim((string) ($_POST['ruleset_code'] ?? ''));
        $label = trim((string) ($_POST['ruleset_label'] ?? ''));
        if ($code === '' && $label !== '') {
            $code = ygo_slugify($label);
        }
        $values = [
            $seasonId,
            $code,
            $label,
            trim((string) ($_POST['ruleset_family'] ?? 'custom')),
            trim((string) ($_POST['ruleset_banlist_name'] ?? '')),
            trim((string) ($_POST['ruleset_banlist_version'] ?? '')),
            ($_POST['ruleset_banlist_effective_on'] ?? '') ?: null,
            trim((string) ($_POST['ruleset_match_format'] ?? '1v1')),
            max(1, min(10, (int) ($_POST['ruleset_best_of'] ?? 3))),
            max(1, min(3, (int) ($_POST['ruleset_participants_per_side'] ?? 1))),
            trim((string) ($_POST['ruleset_schedule_strategy'] ?? 'manual')),
            trim((string) ($_POST['ruleset_deck_pool'] ?? 'all_active')),
            trim((string) ($_POST['ruleset_victory_condition'] ?? '')),
            trim((string) ($_POST['ruleset_defeat_condition'] ?? '')),
            trim((string) ($_POST['ruleset_draw_condition'] ?? '')),
            trim((string) ($_POST['ruleset_notes'] ?? '')),
        ];
        if ($values[1] !== '' && $values[2] !== '') {
            $pdo->prepare("
                INSERT INTO sx_ygo_rulesets
                    (season_id, code, label, format_family, banlist_name, banlist_version, banlist_effective_on, match_format, best_of, participants_per_side, schedule_strategy, deck_pool, victory_condition, defeat_condition, draw_condition, notes, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE label=VALUES(label), format_family=VALUES(format_family), banlist_name=VALUES(banlist_name), banlist_version=VALUES(banlist_version), banlist_effective_on=VALUES(banlist_effective_on), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), schedule_strategy=VALUES(schedule_strategy), deck_pool=VALUES(deck_pool), victory_condition=VALUES(victory_condition), defeat_condition=VALUES(defeat_condition), draw_condition=VALUES(draw_condition), notes=VALUES(notes), active=1
            ")->execute($values);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['ruleset', null, 'save_ruleset', json_encode(['code' => $code, 'label' => $label], JSON_UNESCAPED_UNICODE)]);
        }
        return 'reglamento';
    }
    if ($action === 'save_game_rule') {
        $seasonId = ygo_current_season_id($pdo);
        $ruleId = (int) ($_POST['game_rule_id'] ?? 0);
        $ruleName = trim((string) ($_POST['game_rule_name'] ?? ''));
        $ruleCode = trim((string) ($_POST['game_rule_code'] ?? ''));
        if ($ruleCode === '' && $ruleName !== '') {
            $ruleCode = strtoupper(str_replace('-', '_', ygo_slugify($ruleName)));
        }
        $values = [
            $seasonId,
            $ruleCode,
            $ruleName,
            trim((string) ($_POST['game_rule_type'] ?? 'custom')),
            trim((string) ($_POST['game_rule_scope'] ?? 'general')),
            trim((string) ($_POST['game_rule_trigger'] ?? '')),
            trim((string) ($_POST['game_rule_effect'] ?? '')),
            (int) ($_POST['game_rule_points'] ?? 0),
            max(1, (int) ($_POST['game_rule_priority'] ?? 100)),
            isset($_POST['game_rule_active']) ? 1 : 0,
            trim((string) ($_POST['game_rule_notes'] ?? '')),
        ];
        if ($ruleCode !== '' && $ruleName !== '') {
            if ($ruleId > 0) {
                $pdo->prepare('UPDATE sx_ygo_game_rules SET code=?, name=?, rule_type=?, scope=?, trigger_event=?, effect=?, points_value=?, priority_order=?, active=?, notes=? WHERE id=? AND season_id=?')
                    ->execute(array_merge(array_slice($values, 1), [$ruleId, $seasonId]));
            } else {
                $pdo->prepare('
                    INSERT INTO sx_ygo_game_rules
                        (season_id, code, name, rule_type, scope, trigger_event, effect, points_value, priority_order, active, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), rule_type=VALUES(rule_type), scope=VALUES(scope), trigger_event=VALUES(trigger_event), effect=VALUES(effect), points_value=VALUES(points_value), priority_order=VALUES(priority_order), active=VALUES(active), notes=VALUES(notes)
                ')->execute($values);
                $ruleLookup = $pdo->prepare('SELECT id FROM sx_ygo_game_rules WHERE season_id = ? AND code = ? LIMIT 1');
                $ruleLookup->execute([$seasonId, $ruleCode]);
                $ruleId = (int) $ruleLookup->fetchColumn();
            }
            if ($ruleId > 0) {
                $conditionIds = $_POST['condition_id'] ?? [];
                $conditionTypes = $_POST['condition_type'] ?? [];
                $conditionLabels = $_POST['condition_label'] ?? [];
                $conditionOperators = $_POST['condition_operator'] ?? [];
                $conditionTargets = $_POST['condition_target'] ?? [];
                $conditionResults = $_POST['condition_result'] ?? [];
                $conditionStatuses = $_POST['condition_status'] ?? [];
                $conditionScores = $_POST['condition_score'] ?? [];
                $conditionMessages = $_POST['condition_failure_message'] ?? [];
                $conditionActive = $_POST['condition_active'] ?? [];
                $pdo->prepare('UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?')->execute([$ruleId]);
                $conditionUpsert = $pdo->prepare('
                    INSERT INTO sx_ygo_game_rule_conditions
                        (game_rule_id, condition_type, condition_label, operator, target_value, result_value, status_value, score_value, failure_message, sort_order, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $conditionUpdate = $pdo->prepare('
                    UPDATE sx_ygo_game_rule_conditions
                    SET condition_type=?, condition_label=?, operator=?, target_value=?, result_value=?, status_value=?, score_value=?, failure_message=?, sort_order=?, active=?
                    WHERE id=? AND game_rule_id=?
                ');
                $allowedTypes = ['result_case', 'victory', 'defeat', 'draw', 'validation', 'score', 'availability', 'custom'];
                $allowedOperators = ['sequence_equals', 'defined', 'winner_in', 'winner_equals', 'score_min', 'score_max', 'points_value', 'requires_previous', 'blocks_when', 'custom'];
                $allowedStatuses = ['FINALIZADA', 'REQUIERE D5', 'EN CURSO', 'PENDIENTE', 'INVALID'];
                $rows = max(count((array) $conditionLabels), count((array) $conditionTypes));
                for ($i = 0; $i < $rows; $i++) {
                    $label = trim((string) ($conditionLabels[$i] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $type = trim((string) ($conditionTypes[$i] ?? 'validation'));
                    $operator = trim((string) ($conditionOperators[$i] ?? 'defined'));
                    $type = in_array($type, $allowedTypes, true) ? $type : 'validation';
                    $operator = in_array($operator, $allowedOperators, true) ? $operator : 'defined';
                    $rowActive = in_array((string) ($conditionActive[$i] ?? '0'), ['1', 'on', 'true'], true) ? 1 : 0;
                    $target = trim((string) ($conditionTargets[$i] ?? ''));
                    $result = trim((string) ($conditionResults[$i] ?? ''));
                    $result = in_array($result, ['A', 'B', 'E'], true) ? $result : '';
                    $statusValue = trim((string) ($conditionStatuses[$i] ?? 'FINALIZADA'));
                    $statusValue = in_array($statusValue, $allowedStatuses, true) ? $statusValue : 'FINALIZADA';
                    $scoreValue = trim((string) ($conditionScores[$i] ?? ''));
                    $message = trim((string) ($conditionMessages[$i] ?? ''));
                    $sort = ($i + 1) * 10;
                    $conditionId = (int) ($conditionIds[$i] ?? 0);
                    if ($conditionId > 0) {
                        $conditionUpdate->execute([$type, $label, $operator, $target, $result, $statusValue, $scoreValue, $message, $sort, $rowActive, $conditionId, $ruleId]);
                    } else {
                        $conditionUpsert->execute([$ruleId, $type, $label, $operator, $target, $result, $statusValue, $scoreValue, $message, $sort, $rowActive]);
                    }
                }
            }
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['game_rule', $ruleId ?: null, 'save_game_rule', json_encode(['code' => $ruleCode, 'name' => $ruleName], JSON_UNESCAPED_UNICODE)]);
        }
        return 'reglamento';
    }
    if ($action === 'archive_game_rule') {
        $seasonId = ygo_current_season_id($pdo);
        $ruleId = (int) ($_POST['game_rule_id'] ?? 0);
        if ($ruleId > 0) {
            $pdo->prepare('UPDATE sx_ygo_game_rules SET active=0 WHERE id=? AND season_id=?')->execute([$ruleId, $seasonId]);
            $pdo->prepare('UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?')->execute([$ruleId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['game_rule', $ruleId, 'archive_game_rule', '{}']);
        }
        return 'reglamento';
    }
    if ($action === 'archive_competition') {
        $id = (int) ($_POST['competition_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE id=?")->execute([$id]);
        }
        return 'evento_archivado';
    }
    if ($action === 'save_competition_dependency') {
        $seasonId = ygo_current_season_id($pdo);
        $competitionId = (int) ($_POST['dependency_competition_id'] ?? 0);
        $dependsOnId = (int) ($_POST['dependency_depends_on_id'] ?? 0);
        $condition = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['dependency_condition'] ?? 'completed_with_results')));
        if (!in_array($condition, ['completed', 'has_results', 'completed_with_results', 'top_n', 'winner_to_final', 'defending_champion', 'creates_divisions'], true)) {
            $condition = 'completed_with_results';
        }
        $takeCount = max(0, min(128, (int) ($_POST['dependency_take_count'] ?? 0)));
        if ($condition === 'top_n' && $takeCount <= 0) {
            $takeCount = 4;
        }
        if ($competitionId > 0 && $dependsOnId > 0 && $competitionId !== $dependsOnId) {
            $pdo->prepare("
                INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
            ")->execute([$seasonId, $competitionId, $dependsOnId, $condition, $takeCount]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition', $competitionId, 'save_dependency', json_encode(['depends_on' => $dependsOnId, 'condition' => $condition, 'take_count' => $takeCount], JSON_UNESCAPED_UNICODE)]);
        }
        return 'dependencia_evento';
    }
    if ($action === 'delete_competition_dependency') {
        $dependencyId = (int) ($_POST['dependency_id'] ?? 0);
        if ($dependencyId > 0) {
            $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE id=?')->execute([$dependencyId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition_dependency', $dependencyId, 'delete_dependency', '{}']);
        }
        return 'dependencia_quitada';
    }
    if ($action === 'set_qualifier_status') {
        $seasonId = ygo_current_season_id($pdo);
        $competitionId = ygo_selected_qualifier_competition_id($pdo);
        $status = (string) ($_POST['status'] ?? 'active');
        if (!in_array($status, ['draft', 'active', 'completed'], true)) {
            $status = 'active';
        }
        if ($status === 'completed') {
            if (!ygo_finalize_qualifier($pdo, $competitionId)) {
                return 'clasificatorio_incompleto';
            }
        }
        if ($competitionId) {
            $currentStmt = $pdo->prepare("SELECT status FROM sx_ygo_competitions WHERE id=? AND season_id=? AND type='qualifier' AND active=1 LIMIT 1");
            $currentStmt->execute([$competitionId, $seasonId]);
            $fromStatus = (string) ($currentStmt->fetchColumn() ?: '');
            $pdo->prepare("UPDATE sx_ygo_competitions SET status=? WHERE id=? AND season_id=? AND type='qualifier' AND active=1")->execute([$status, $competitionId, $seasonId]);
            ygo_record_competition_status_history($pdo, $seasonId, $competitionId, $fromStatus, $status, 'set_qualifier_status');
        }
        return 'clasificatorio_' . $status;
    }
    return null;
}
