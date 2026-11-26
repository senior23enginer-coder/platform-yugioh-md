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
    ['seedCounts' => $seedCounts, 'seasonId' => $seasonId, 'catalogGroups' => $catalogGroups] = \StationX\Yugioh\Infrastructure\Composition\BootstrapModule::initialize($pdo)->execute($divisions, $fixtures);
    $competitionTypeOptions = ygo_catalog_competition_type_options($catalogGroups['competition_type'] ?? []);
    $eventRulesetOptions = ygo_catalog_label_options($catalogGroups['ruleset'] ?? []);
    $eventDeckPoolOptions = ygo_catalog_label_options($catalogGroups['deck_pool'] ?? []);
    $competitionStatusOptions = ygo_catalog_label_options($catalogGroups['competition_status'] ?? []);
    $matchStatusOptions = ygo_catalog_label_options($catalogGroups['match_status'] ?? []);
    $schedulePatternOptions = ygo_catalog_label_options($catalogGroups['schedule_pattern'] ?? []);
    ['deletedSeedDivisions' => $deletedSeedDivisions, 'goatLeagueStructure' => $goatLeagueStructure, 'octoberScheduleMigration' => $octoberScheduleMigration, 'activatedDueCompetitions' => $activatedDueCompetitions, 'teamSyncCount' => $teamSyncCount, 'deckFormatSyncCount' => $deckFormatSyncCount, 'deckAnalysisBaselineCount' => $deckAnalysisBaselineCount] = \StationX\Yugioh\Infrastructure\Composition\BootstrapModule::maintain($pdo)->execute($seasonId, $divisionDefinitions, $eventRulesetOptions);
    \StationX\Yugioh\Infrastructure\Composition\BootstrapModule::backfills($pdo)->execute();
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
        ['screen' => $targetScreen, 'extraQuery' => $extraQuery] = \StationX\Yugioh\Application\Bootstrap\PostRedirectTarget::resolve($posted, $_POST);
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
    $resolvedFlash = \StationX\Yugioh\Application\Bootstrap\SavedStatusMessage::resolve(
        $savedStatus,
        isset($_SESSION['ygo_season_error']) ? (string) $_SESSION['ygo_season_error'] : null,
        $flashMessage,
        $flashClass,
    );
    $flashMessage = $resolvedFlash['message'];
    $flashClass = $resolvedFlash['class'];
    if ($resolvedFlash['consumeSeasonError']) {
        unset($_SESSION['ygo_season_error']);
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
