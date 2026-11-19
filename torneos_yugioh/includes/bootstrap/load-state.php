<?php
declare(strict_types=1);

// Legacy request bootstrap, POST dispatch and database loading; preserve transaction boundaries.
$fixtures = [];
$allDecks = [];
foreach ($divisions as $key => $division) {
    $fixtures[$key] = buildFixtures($division['decks']);
    foreach ($division['decks'] as $deck) {
        $allDecks[] = ['rank' => $deck[0], 'name' => $deck[1], 'division' => $key, 'reason' => $deck[2]];
    }
}

$defaultUiLabels = [];
foreach (ygo_default_ui_labels() as $defaultUiLabel) {
    $defaultUiLabels[$defaultUiLabel[0]] = ['id' => 0, 'key' => $defaultUiLabel[0], 'title' => $defaultUiLabel[1], 'subtitle' => $defaultUiLabel[2], 'active' => 1];
}
$appData = ['eventExtendedCatalog'=>ygo_extended_event_catalog(), 'executionCapabilities'=>ygo_execution_capabilities(), 'executionModelsByType'=>array_combine(array_keys($competitionTypeOptions),array_map('ygo_allowed_execution_models_for_type',array_keys($competitionTypeOptions))), 'divisions' => $divisions, 'fixtures' => $fixtures, 'allDecks' => $allDecks, 'teams' => [], 'competitions' => [], 'competitionDependencies' => [], 'statusHistory' => [], 'standingSnapshots' => [], 'matchSnapshots' => [], 'matchupAnalyses' => [], 'matchupAnalysisHistory' => [], 'deckAnalysisProfiles' => [], 'deckAnalysisProfileHistory' => [], 'medalUnlocks' => [], 'medalProgress' => [], 'rankingHistory' => [], 'eventSummaries' => [], 'checkins' => [], 'metaHistory' => [], 'eloHistory' => [], 'seasons' => [], 'players' => [], 'playerDecks' => [], 'cards' => [], 'media' => [], 'logoHistory' => [], 'honors' => [], 'audit' => [], 'events' => [], 'rulesets' => [], 'rulesetVersions' => [], 'matchTypes' => [], 'gameRules' => [], 'gameRuleConditions' => [], 'eventPromptTemplates' => ygo_event_available_templates(), 'competitionTypeOptions' => $competitionTypeOptions, 'settings' => ygo_default_settings(), 'uiLabels' => $defaultUiLabels, 'serverToday' => date('Y-m-d')];
$dbStatus = ['connected' => false, 'message' => 'Sin conexion comprobada', 'counts' => []];
$flashMessage = null;
$flashClass = 'alert-success';
try {
    $prefix = '';
    $entity = 1;
    $pdo = connect_store_common($prefix, $entity);
    ensure_ygo_tables($pdo);
    ygo_add_column($pdo, 'sx_ygo_matches', 'completed_at', 'DATETIME NULL');
    ygo_stats_schema($pdo);
    ygo_ffa_schema($pdo);
    ygo_stats_bootstrap_global($pdo);
    ygo_add_column($pdo, 'sx_ygo_competitions', 'execution_parameters', 'LONGTEXT NULL');
    $seedCounts = seed_ygo_database($pdo, $divisions, $fixtures);
    ygo_ensure_seasons_schema($pdo);
    ygo_season_initialize_context($pdo);
    ygo_season_close_expired($pdo);
    $seasonId = ygo_current_season_id($pdo);
    ygo_ensure_default_catalogs($pdo, $seasonId);
    $catalogGroups = ygo_load_catalog_groups($pdo, $seasonId);
    $competitionTypeOptions = ygo_catalog_competition_type_options($catalogGroups['competition_type'] ?? []);
    $eventRulesetOptions = ygo_catalog_label_options($catalogGroups['ruleset'] ?? []);
    $eventDeckPoolOptions = ygo_catalog_label_options($catalogGroups['deck_pool'] ?? []);
    $competitionStatusOptions = ygo_catalog_label_options($catalogGroups['competition_status'] ?? []);
    $matchStatusOptions = ygo_catalog_label_options($catalogGroups['match_status'] ?? []);
    $schedulePatternOptions = ygo_catalog_label_options($catalogGroups['schedule_pattern'] ?? []);
    ygo_ensure_default_settings($pdo);
    ygo_ensure_default_ui_labels($pdo);
    $deletedSeedDivisions = ygo_remove_seeded_divisions($pdo, $seasonId, $divisionDefinitions);
    ygo_ensure_default_rulesets($pdo, $seasonId, $eventRulesetOptions);
    ygo_ensure_default_ruleset_versions($pdo, $seasonId);
    ygo_ensure_default_match_types($pdo);
    ygo_ensure_default_game_rules($pdo, $seasonId);
    $goatLeagueStructure = ygo_ensure_goat_league_structure($pdo, $seasonId);
    $octoberScheduleMigration = ygo_apply_october_2026_schedule($pdo);
    $activatedDueCompetitions = ygo_activate_due_scheduled_competitions($pdo);
    ygo_ensure_sequential_competition_codes($pdo, $seasonId);
    $teamSyncCount = ygo_sync_deck_teams($pdo);
    $deckFormatSyncCount = ygo_normalize_existing_decks_to_tcg_advanced($pdo);
    $deckAnalysisBaselineCount = ygo_seed_deck_analysis_profiles($pdo);
    $medalBackfillVersion = 'medal_backfill_v2';
    $medalBackfillStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE action = ?');
    $medalBackfillStmt->execute([$medalBackfillVersion]);
    if ((int) $medalBackfillStmt->fetchColumn() === 0) {
        $unlockedCount = ygo_sync_medal_unlocks($pdo);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['system', 0, $medalBackfillVersion, json_encode(['unlocks_processed' => $unlockedCount], JSON_UNESCAPED_UNICODE)]);
    }
    $snapshotBackfillVersion = 'snapshot_backfill_v1';
    $snapshotBackfillStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE action = ?');
    $snapshotBackfillStmt->execute([$snapshotBackfillVersion]);
    if ((int) $snapshotBackfillStmt->fetchColumn() === 0) {
        $snapshotCount = ygo_backfill_match_snapshots($pdo);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['system', 0, $snapshotBackfillVersion, json_encode(['snapshots_inferred' => $snapshotCount], JSON_UNESCAPED_UNICODE)]);
    }
    $intelligenceVersion = 'historical_intelligence_v2';
    $intelligenceStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE action=?');
    $intelligenceStmt->execute([$intelligenceVersion]);
    if ((int) $intelligenceStmt->fetchColumn() === 0) {
        $metaRows = ygo_sync_meta_history($pdo);
        $eloRows = ygo_sync_elo_history($pdo);
        $rankingRows = ygo_sync_global_ranking_history($pdo);
        $summaryRows = ygo_sync_event_summaries($pdo);
        ygo_sync_medal_unlocks($pdo);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type,entity_id,action,payload) VALUES (?,?,?,?)')->execute(['system',0,$intelligenceVersion,json_encode(['meta_rows'=>$metaRows,'elo_rows'=>$eloRows],JSON_UNESCAPED_UNICODE)]);
    }
    if ((string) ($_GET['api'] ?? '') === 'duel_session') {
        ygo_live_duel_api($pdo);
    }
    if ((string) ($_GET['api'] ?? '') === 'match_editor') {
        ygo_match_editor_api($pdo);
    }
    if ((string) ($_GET['api'] ?? '') === 'event_audit') ygo_event_audit_api($pdo);
    if ((string) ($_GET['api'] ?? '') === 'event_logo') ygo_event_logo_api($pdo);
    if ((string) ($_GET['api'] ?? '') === 'deck_builder') ygo_deck_builder_api($pdo);
    try { $posted = ygo_handle_post($pdo); }
    catch(InvalidArgumentException $validationError) {
        if($pdo->inTransaction())$pdo->rollBack();
        $posted=null;$flashMessage=$validationError->getMessage();$flashClass='alert-warning';
    }
    if ($posted !== null) {
        ygo_sync_event_summaries($pdo);
    }
    $dbStatus = [
        'connected' => true,
        'message' => 'Conectado a la misma base de datos de la tienda',
        'counts' => array_merge($seedCounts, ['deleted_seed_divisions' => $deletedSeedDivisions, 'deck_teams_sync' => $teamSyncCount, 'deck_format_sync' => $deckFormatSyncCount, 'deck_analysis_baselines' => $deckAnalysisBaselineCount, 'goat_disabled_qualifiers' => $goatLeagueStructure['disabled_qualifiers'] ?? 0, 'shared_schedule_limit' => $octoberScheduleMigration['global_daily_limit'] ?? 3, 'auto_started_events' => $activatedDueCompetitions]),
    ];
    if ($posted !== null) {
        $targetScreen = preg_match('/^jugador_\d+$/', $posted) ? 'estadisticas' : (preg_match('/^(?:deck|deck_analysis|logo_deck|matchup_deck)_\d+$/', $posted) ? 'deck_ficha' : (preg_match('/^(?:equipo|logo_team)_\d+$/', $posted) ? 'equipo_ficha' : (preg_match('/^logo_competition_\d+$/', $posted) ? 'evento_ficha' : (strpos($posted, 'clasificatorio_') === 0 ? 'clasificatorio' : (in_array($posted, ['resultado', 'resultado_bloqueado_fecha', 'resultado_bloqueado_inicio', 'resultado_bloqueado_rendicion'], true) ? 'resultados' : (in_array($posted, ['calendario', 'calendario_reprogramado', 'calendario_bloqueado_finalizado', 'partido_manual', 'partido_invalido', 'amistoso_eliminado', 'amistoso_no_eliminado', 'calendario_bloqueado_regla', 'partido_bloqueado_regla', 'partido_bloqueado_pool'], true) ? 'calendario' : ($posted === 'reglamento' ? 'reglamento' : ($posted === 'reglas' ? 'reglas' : (($posted === 'evento' || strpos($posted, 'evento_configurado_') === 0 || preg_match('/^(evento_avanzado|divisiones_aplicadas|movimientos_liga|ranking_recalculado)_/', $posted)) ? 'evento_ficha' : 'gestion')))))))));
        $postedEventId = 0;
        if (preg_match('/^evento_configurado_(\d+)$/', $posted, $eventMatch)) {
            $postedEventId = (int) $eventMatch[1];
        } elseif (preg_match('/^logo_competition_(\d+)$/', $posted, $eventMatch)) {
            $postedEventId = (int) $eventMatch[1];
        } elseif (preg_match('/^(?:evento_avanzado|divisiones_aplicadas|movimientos_liga|ranking_recalculado)_(\d+)_\d+$/', $posted, $eventMatch)) {
            $postedEventId = (int) $eventMatch[1];
        } elseif ($targetScreen === 'clasificatorio') {
            $postedEventId = (int) ($_POST['qualifier_competition_id'] ?? 0);
        }
        $extraQuery = $postedEventId > 0 ? '&event_id=' . $postedEventId : '';
        if (preg_match('/^(?:deck|deck_analysis|logo_deck|matchup_deck)_(\d+)$/', $posted, $entityMatch)) {
            $extraQuery .= '&deck_id=' . (int) $entityMatch[1];
            if (str_starts_with($posted, 'matchup_deck_') || str_starts_with($posted, 'deck_analysis_')) $extraQuery .= '&deck_view=analysis';
        } elseif (preg_match('/^(?:equipo|logo_team)_(\d+)$/', $posted, $entityMatch)) {
            $extraQuery .= '&team_id=' . (int) $entityMatch[1];
        }
        if (str_starts_with($posted, 'temporada_')) $targetScreen = 'temporadas';
        if ($posted === 'evento_temporada_bloqueada') $targetScreen = 'torneos';
        if (!ygo_season_find($pdo, ygo_current_season_id($pdo))) unset($GLOBALS['ygo_request_season_id']);
        header('Location: ./?saved=' . rawurlencode($posted) . '&season_context=' . ygo_current_season_id($pdo) . $extraQuery . '#' . $targetScreen);
        exit;
    }
    ygo_stats_sync($pdo);
    $appData = ygo_load_data($pdo, $divisions, $fixtures, $allDecks);
    $appData['statisticsV8'] = ygo_stats_view($pdo);
    $appData['executionCapabilities']=ygo_execution_capabilities();
    $appData['executionModelsByType']=array_combine(array_keys($competitionTypeOptions),array_map('ygo_allowed_execution_models_for_type',array_keys($competitionTypeOptions)));
    $appData['ffaMatches']=ygo_ffa_rows($pdo);$appData['canEditEvents']=ygo_can_edit();
    $appData['teamResults']=[];$teamResultsQuery=$pdo->prepare("SELECT setting_key,setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key LIKE 'event_team_result_%'");$teamResultsQuery->execute([ygo_current_season_id($pdo)]);foreach($teamResultsQuery->fetchAll(PDO::FETCH_ASSOC) as $tr)$appData['teamResults'][(int)substr($tr['setting_key'],18)]=json_decode($tr['setting_value'],true);
    foreach($appData['competitions'] as &$workflowCompetition){$workflowCompetition['teamConfig']=ygo_team_load($pdo,$workflowCompetition);$workflowCompetition['workflow']=ygo_workflow_load($pdo,$workflowCompetition);$workflowCompetition['recoveryToken']=$workflowCompetition['workflow']!==null?ygo_workflow_recovery_token($workflowCompetition['workflow']):null;}unset($workflowCompetition);
    $appData['admissionSources']=ygo_admission_sources($pdo);
    foreach($appData['competitions'] as &$admissionCompetition)$admissionCompetition['admission']=ygo_admission_view($pdo,$admissionCompetition);unset($admissionCompetition);
    $appData['eventExtendedCatalog']=ygo_extended_event_catalog();
    $appData['businessEventDescriptors']=json_decode((string)file_get_contents(YGO_APP_ROOT.'/includes/event_business_descriptors.json'),true);
    $appData['catalogs'] = $catalogGroups;
    $appData['eventPromptTemplates'] = ygo_event_available_templates();
    $appData['competitionTypeOptions'] = $competitionTypeOptions;
    $divisions = $appData['divisions'];
    $fixtures = $appData['fixtures'];
    $allDecks = $appData['allDecks'];
    $teams = $appData['teams'];
    $competitions = $appData['competitions'];
    $competitionDependencies = $appData['competitionDependencies'];
    $cards = $appData['cards'];
    $media = $appData['media'];
    $honors = $appData['honors'];
    $players = $appData['players'];
    $playerDecks = $appData['playerDecks'];
    $audit = $appData['audit'];
    $rulesets = $appData['rulesets'];
    $rulesetVersions = $appData['rulesetVersions'];
    $matchTypes = $appData['matchTypes'];
    $gameRules = $appData['gameRules'];
    $settings = $appData['settings'];
    $uiLabels = $appData['uiLabels'] ?? $defaultUiLabels;
    $savedStatus = isset($_GET['saved']) ? (string) $_GET['saved'] : '';
    if (in_array($savedStatus, ['temporada_error','evento_temporada_bloqueada'], true)) {
        $flashMessage = (string) ($_SESSION['ygo_season_error'] ?? 'Revisa la temporada y las fechas del evento.');
        unset($_SESSION['ygo_season_error']);
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'temporada_guardada') {
        $flashMessage = 'Temporada actualizada correctamente.';
    }
    if ($savedStatus === 'resultado_bloqueado_fecha') {
        $flashMessage = 'Resultado bloqueado: el duelo no esta habilitado para ejecutarse en esta fecha segun la configuracion actual.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'resultado_bloqueado_inicio') {
        $flashMessage = 'Resultado bloqueado: primero debes lanzar los dados, definir quien comienza y confirmar Comenzar duelo.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'resultado_bloqueado_rendicion') {
        $flashMessage = 'Rendicion bloqueada: solo puede rendirse el lado que tiene el turno activo.';
        $flashClass = 'alert-warning';
    } elseif (in_array($savedStatus, ['calendario_bloqueado_regla', 'partido_bloqueado_regla'], true)) {
        $flashMessage = 'Calendario bloqueado: la fecha, hora o capacidad del dia no cumple las reglas configuradas.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'calendario_bloqueado_finalizado') {
        $flashMessage = 'No se cambio la fecha: un duelo finalizado conserva su programacion historica.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'calendario_reprogramado') {
        $flashMessage = 'Duelo reprogramado correctamente. El calendario del evento fue actualizado.';
        $flashClass = 'alert-success';
    } elseif ($savedStatus === 'partido_bloqueado_pool') {
        $flashMessage = 'Partido bloqueado: uno o mas decks no pertenecen al pool habilitado para ese evento.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'partido_invalido') {
        $flashMessage = 'No se creo el amistoso: selecciona dos decks diferentes y vuelve a intentarlo.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'partido_manual') {
        $flashMessage = 'Combate amistoso creado y agregado al calendario.';
        $flashClass = 'alert-success';
    } elseif ($savedStatus === 'amistoso_eliminado') {
        $flashMessage = 'Combate amistoso eliminado junto con sus datos asociados.';
        $flashClass = 'alert-success';
    } elseif ($savedStatus === 'amistoso_no_eliminado') {
        $flashMessage = 'No se elimino el combate: solo pueden borrarse amistosos independientes creados manualmente.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'deck_bloqueado_evento') {
        $flashMessage = 'Deck bloqueado: no se puede eliminar porque esta asociado a un evento activo, pendiente, pendiente de evento o finalizado.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'division_minimo_4_decks') {
        $flashMessage = 'Division bloqueada: selecciona minimo 4 decks para crear o guardar una division.';
        $flashClass = 'alert-warning';
    } elseif (preg_match('/^evento_avanzado_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $created = (int) $match[2];
        $flashMessage = $created > 0
            ? 'Siguiente fase creada: ' . $created . ' combates nuevos generados.'
            : 'No se creo una nueva fase: verifica que la fase anterior este finalizada y tenga ganadores.';
        $flashClass = $created > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^divisiones_aplicadas_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $moved = (int) $match[2];
        $flashMessage = $moved > 0
            ? 'Divisiones aplicadas desde clasificatorio: ' . $moved . ' decks asignados.'
            : 'No se aplicaron divisiones: falta finalizar el clasificatorio o configurar rangos validos.';
        $flashClass = $moved > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^movimientos_liga_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $moved = (int) $match[2];
        $flashMessage = $moved > 0
            ? 'Ascensos/descensos aplicados: ' . $moved . ' movimientos registrados.'
            : 'No se aplicaron movimientos: la liga no esta cerrada, no hay cupos configurados o los movimientos estan bloqueados.';
        $flashClass = $moved > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^ranking_recalculado_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $ok = (int) $match[2];
        $flashMessage = $ok > 0
            ? 'Ranking recalculado: snapshots general y por fase actualizados.'
            : 'No se recalculo ranking: el evento no existe o no esta activo.';
        $flashClass = $ok > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^qa_codex_archivado_(\d+)$/', $savedStatus, $match)) {
        $count = (int) $match[1];
        $flashMessage = $count > 0
            ? 'Datos QA Codex archivados: ' . $count . ' registros marcados como inactivos.'
            : 'No habia datos QA Codex activos para archivar.';
        $flashClass = $count > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^gpt_sync_(\d+)_(\d+)_(\d+)$/', $savedStatus, $gptResult)) {
        $flashMessage = 'Deck ' . $gptResult[1] . ' sincronizado con GPT: ' . $gptResult[2] . ' archivos creados, ' . $gptResult[3] . ' conservados sin cambios. Los prompts NO fueron ejecutados.';
        $flashClass = 'alert-success';
    } elseif ($savedStatus !== '') {
        $flashMessage = 'Cambio guardado: ' . $savedStatus;
        $flashClass = 'alert-success';
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $dbStatus = [
        'connected' => false,
        'message' => 'No se pudo conectar a la base de datos en este entorno',
        'counts' => [],
    ];
    $appData = ['eventExtendedCatalog'=>ygo_extended_event_catalog(), 'executionCapabilities'=>ygo_execution_capabilities(), 'executionModelsByType'=>array_combine(array_keys($competitionTypeOptions),array_map('ygo_allowed_execution_models_for_type',array_keys($competitionTypeOptions))), 'divisions' => [], 'fixtures' => [], 'allDecks' => [], 'teams' => [], 'competitions' => [], 'competitionDependencies' => [], 'statusHistory' => [], 'standingSnapshots' => [], 'cards' => [], 'media' => [], 'logoHistory' => [], 'honors' => [], 'audit' => [], 'events' => [], 'rulesets' => [], 'rulesetVersions' => [], 'matchTypes' => [], 'gameRules' => [], 'gameRuleConditions' => [], 'eventPromptTemplates' => ygo_event_available_templates(), 'competitionTypeOptions' => $competitionTypeOptions, 'settings' => ygo_default_settings(), 'uiLabels' => $defaultUiLabels, 'serverToday' => date('Y-m-d')];
    $divisions = [];
    $fixtures = [];
    $allDecks = [];
    $teams = [];
    $competitions = [];
    $competitionDependencies = [];
    $cards = [];
    $media = [];
    $honors = [];
    $audit = [];
    $rulesets = [];
    $rulesetVersions = [];
    $matchTypes = [];
    $gameRules = [];
    $settings = ygo_default_settings();
    $uiLabels = $defaultUiLabels;
}

$uiLabels = $uiLabels ?? ($appData['uiLabels'] ?? $defaultUiLabels);
$uiText = static function (string $key, string $field, string $fallback) use ($uiLabels): string {
    if (isset($uiLabels[$key]['active']) && (int) $uiLabels[$key]['active'] !== 1) {
        return $fallback;
    }
    $value = trim((string) ($uiLabels[$key][$field] ?? ''));
    return $value !== '' ? $value : $fallback;
};
