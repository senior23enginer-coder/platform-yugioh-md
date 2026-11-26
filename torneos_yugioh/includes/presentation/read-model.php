<?php
declare(strict_types=1);

// Presentation projections derived from the loaded request state.
$appData['dbStatus'] = $dbStatus;
$appData['eventLogoGallery'] = ygo_event_logo_gallery();
$displayDivisions = \StationX\Yugioh\Application\Presentation\DirectoryProjection::divisions($divisions);
$appData['presentationLabels'] = ygo_ui_labels_catalog($divisions, $competitions);
$presentationLabels = $appData['presentationLabels'];
$deckTeamByName = \StationX\Yugioh\Application\Presentation\DirectoryProjection::deckTeams($allDecks);
$deckMatchLabel = static fn(string $deckName): string =>
    \StationX\Yugioh\Application\Presentation\DirectoryProjection::matchLabel($deckTeamByName, $deckName);
$matchOptions = [];
foreach ($fixtures as $code => $rounds) {
    foreach ($rounds as $round) {
        foreach ($round['matches'] as $match) {
            $matchOptions[] = [
                'id' => (int) ($match['id'] ?? 0),
                'label' => ygo_ui_match_label($match, (string)$code, $round['ronda'], $presentationLabels) . ': ' . $deckMatchLabel((string) $match['a']) . ' vs ' . $deckMatchLabel((string) $match['b']),
            ];
        }
    }
}

$qualifierCompetition = null;
$qualifierCompetitions = [];
foreach ($competitions as $competition) {
    if (($competition['type'] ?? '') === 'qualifier') {
        $qualifierCompetitions[] = $competition;
        if ($qualifierCompetition === null) {
            $qualifierCompetition = $competition;
        }
    }
}
$pendingEventQualifiers = array_values(array_filter($qualifierCompetitions, 'ygo_is_pending_event_qualifier_candidate'));
$activeQualifierCompetition = null;
foreach ($qualifierCompetitions as $competition) {
    $statusKey = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
    if (in_array($statusKey, ['active', 'activo', 'en_curso'], true) && (($competition['can_execute'] ?? true) !== false)) {
        $activeQualifierCompetition = $competition;
        break;
    }
}
$requestedQualifierId = max(0, (int) ($_GET['event_id'] ?? 0));
$requestedQualifierCompetition = null;
foreach ($qualifierCompetitions as $competition) {
    if ((int) ($competition['id'] ?? 0) === $requestedQualifierId) {
        $requestedQualifierCompetition = $competition;
        break;
    }
}
$showQualifierDetail = $requestedQualifierCompetition !== null;
$qualifierCompetitionIdMap = array_fill_keys(array_map(static fn(array $competition): int => (int) ($competition['id'] ?? 0), $qualifierCompetitions), true);
$qualifierStatsByCompetition = \StationX\Yugioh\Application\Presentation\QualifierMatchProjection::statistics($fixtures, $qualifierCompetitionIdMap);
$displayQualifierCompetition = $requestedQualifierCompetition ?? $activeQualifierCompetition ?? $qualifierCompetition;
$displayQualifierId = (int) ($displayQualifierCompetition['id'] ?? 0);
$displayQualifierSelectedIds = array_map('intval', (array) ($displayQualifierCompetition['selected_deck_ids'] ?? []));
$displayQualifierSelectedMap = array_fill_keys($displayQualifierSelectedIds, true);
$displayQualifierDecks = $displayQualifierSelectedIds
    ? array_values(array_filter($allDecks, fn(array $deck): bool => isset($displayQualifierSelectedMap[(int) ($deck['id'] ?? 0)])))
    : [];
$displayQualifierRequiredDuels = max(2, min(5, (int) (($displayQualifierCompetition['qualifier_duels_per_deck'] ?? 0) ?: 3)));
$displayQualifierMatchesPerDay = max(1, min(5, (int) (($displayQualifierCompetition['qualifier_matches_per_day'] ?? 0) ?: 1)));
[
    'matches' => $displayQualifierMatches,
    'roundCount' => $displayQualifierRoundCount,
    'scheduledCount' => $displayQualifierScheduledCount,
    'finishedCount' => $displayQualifierFinishedCount,
] = \StationX\Yugioh\Application\Presentation\QualifierMatchProjection::display($fixtures, $displayQualifierId);
$qualifierStatus = ygo_normalize_status_key((string) ($displayQualifierCompetition['status'] ?? 'draft'));
$qualifierFinished = in_array($qualifierStatus, ['completed', 'finalizada', 'finalizado', 'closed', 'archived'], true);
$qualifierStatusLabel = $qualifierFinished ? 'Finalizado' : ($qualifierStatus === 'pending_event' ? 'Pendiente evento' : (in_array($qualifierStatus, ['active', 'activo', 'en_curso'], true) ? 'Activo' : 'Pendiente configuracion'));
$divisionModeLabel = $qualifierFinished ? 'Formatos oficiales' : 'Formatos pendientes del clasificatorio';
$cupEventTypes = ['cup', 'final'];
$cupCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => in_array((string) ($competition['type'] ?? ''), $cupEventTypes, true)));
['matches' => $cupMatchesByCompetition, 'stats' => $cupStatsByCompetition] =
    \StationX\Yugioh\Application\Presentation\CompetitionMatchProjection::cups($fixtures);
$cupActiveCount = count(array_filter($cupCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['active', 'activo', 'en_curso'], true)));
$cupCompletedCount = count(array_filter($cupCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['completed', 'finalized', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'], true)));
$cupTotalMatches = array_sum(array_map(static fn(array $stats): int => (int) ($stats['total'] ?? 0), $cupStatsByCompetition));
$featuredCupCompetition = $cupCompetitions[0] ?? null;
$worldCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => (string) ($competition['type'] ?? '') === 'world'));
['matches' => $worldMatchesByCompetition, 'stats' => $worldStatsByCompetition] =
    \StationX\Yugioh\Application\Presentation\CompetitionMatchProjection::worlds($fixtures);
$worldActiveCount = count(array_filter($worldCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['active', 'activo', 'en_curso'], true)));
$worldTotalMatches = array_sum(array_map(static fn(array $stats): int => (int) ($stats['total'] ?? 0), $worldStatsByCompetition));
[
    'byType' => $eventSummaryByType,
    'byRuleset' => $eventSummaryByRuleset,
    'byStatus' => $eventSummaryByStatus,
    'byId' => $competitionById,
    'dependencies' => $dependencyFlow,
] = \StationX\Yugioh\Application\Presentation\CompetitionDirectoryProjection::build($competitions);
$rulesetSelectOptions = [];
foreach ($rulesets as $ruleset) {
    $rulesetSelectOptions[(string) ($ruleset['code'] ?? '')] = (string) ($ruleset['label'] ?? $ruleset['code'] ?? '');
}
if (!$rulesetSelectOptions) {
    $rulesetSelectOptions = $eventRulesetOptions;
}
$formatSelectOptions = array_filter(
    $rulesetSelectOptions,
    static fn(string $label, string $code): bool => $code !== '' && $code !== 'custom',
    ARRAY_FILTER_USE_BOTH
);
$deckFormatCodesById = [];
if (!empty($dbStatus['connected']) && isset($pdo)) {
    foreach ($formatSelectOptions as $formatCode => $formatLabel) {
        foreach (ygo_deck_ids_for_ruleset($pdo, (string) $formatCode) as $formatDeckId) {
            $deckFormatCodesById[$formatDeckId][] = (string) $formatCode;
        }
    }
}
foreach ($allDecks as &$formatDeck) {
    $formatDeck['format_codes'] = array_values(array_unique($deckFormatCodesById[(int) ($formatDeck['id'] ?? 0)] ?? []));
}
unset($formatDeck);
$appData['allDecks'] = $allDecks;
$appData['leagueDeckAssignments'] = !empty($dbStatus['connected']) && isset($pdo) ? ygo_league_deck_assignments($pdo) : [];
$appData['currentSeasonId'] = $dbStatus['connected'] ? ygo_current_season_id($pdo) : 0;
$rulesetVersionCatalog = $rulesetVersions ?: ygo_ruleset_version_catalog();
$matchTypeOptions = $matchTypes ?: [
    ['code' => '1v1', 'label' => '1v1', 'participants_per_side' => 1],
    ['code' => '2v2', 'label' => '2v2', 'participants_per_side' => 2],
    ['code' => '3v3', 'label' => '3v3', 'participants_per_side' => 3],
];
$todayIso = date('Y-m-d');
$currentEventsByFormat = \StationX\Yugioh\Application\Presentation\DashboardProjection::eventsByFormat($competitions, $eventRulesetOptions);
$recentResults = \StationX\Yugioh\Application\Presentation\DashboardProjection::recentResults($fixtures, $todayIso, $presentationLabels, 'ygo_ui_match_label');
[$activeDashboardEvents, $activeEventCount, $totalFixtureMatches, $totalFinalizedMatches] = \StationX\Yugioh\Application\Presentation\DashboardProjection::fixtureCounts($currentEventsByFormat, $fixtures);
$pendingConfigEventCount = count(array_filter($competitions, static fn(array $competition): bool => ygo_normalize_status_key((string) ($competition['status'] ?? '')) === 'pending_configuration'));
$pendingEventCount = count(array_filter($competitions, static fn(array $competition): bool => ygo_normalize_status_key((string) ($competition['status'] ?? '')) === 'pending_event'));
$completedEventCount = count(array_filter($competitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['completed', 'finalized', 'finalizado', 'finalizada'], true)));
$cancelledEventCount = count(array_filter($competitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['cancelled', 'canceled', 'anulado', 'anulada'], true)));
$activeGameRuleCount = count(array_filter($gameRules, static fn(array $rule): bool => !empty($rule['active'])));
$deckWithTeamCount = count(array_filter($allDecks, static fn(array $deck): bool => !empty($deck['team_id'])));
$deckWithoutTeamCount = max(0, count($allDecks) - $deckWithTeamCount);
$deckWithMediaCount = count(array_filter($allDecks, static fn(array $deck): bool => !empty($deck['cover_url']) || !empty($deck['key_cards'])));
$eventsWithoutDateCount = count(array_filter($competitions, static fn(array $competition): bool => empty($competition['starts_on'])));
$scheduledFixtureMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count(array_filter($round['matches'] ?? [], static fn(array $match): bool => !empty($match['scheduled_on']))), $rounds)), $fixtures));
$nextDashboardEvent = $activeDashboardEvents[0] ?? ($competitions[0] ?? null);
// The application depends on a season read port, not on SQL or an event record.
$currentDashboardSeason = (new \StationX\Yugioh\Application\Seasons\GetCurrentSeason(
    new \StationX\Yugioh\Infrastructure\Persistence\LoadedSeasonRepository($seasons ?? [])
))->execute();
$dashboardSeasonName = $currentDashboardSeason ? trim($currentDashboardSeason->name) : '2027 B';
[$dashboardMatches, $dashboardMatchStatsByCompetition, $dashboardMatchStatsByPhase, $dashboardDeckUsage, $upcomingDashboardMatches] = \StationX\Yugioh\Application\Presentation\DashboardProjection::matches($fixtures, $presentationLabels, $deckMatchLabel, 'ygo_ui_match_label', date('Y-m-d'));
[$teamDeckCounts, $teamDashboardLogos, $dashboardLiveRanking, $dashboardRankingSource, $topDashboardDecks, $topDashboardTeamPool, $topDashboardTeams] = \StationX\Yugioh\Application\Presentation\DashboardProjection::teamsAndRanking($allDecks, $teams, $activeDashboardEvents, 'ygo_media_src');
[$qualifierPairings, $qualifierProgress, $displayQualifierRanking, $displayQualifierRankingByDeckId, $qualifierReadyCount] = \StationX\Yugioh\Application\Presentation\QualifierProgressProjection::build($displayQualifierDecks, $displayQualifierRequiredDuels, $displayQualifierCompetition, $fixtures, $displayQualifierId, 'ygo_media_src');
$qualifierFinalizeCheck = ['ok' => false, 'reasons' => ['Conecta la base de datos para validar el cierre.']];
if ($dbStatus['connected'] && isset($pdo)) {
    try {
        $qualifierFinalizeCheck = ygo_qualifier_finalize_check($pdo, $displayQualifierId);
    } catch (Throwable $error) {
        $qualifierFinalizeCheck = ['ok' => false, 'reasons' => ['No se pudo validar el cierre del clasificatorio.']];
    }
}
$auditSummary = \StationX\Yugioh\Application\Presentation\AuditSummaryProjection::build($audit ?? []);

$duelPlayerSide = strtoupper((string) ($_GET['duel_player'] ?? ''));
