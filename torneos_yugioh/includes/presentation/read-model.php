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
$matchOptions = \StationX\Yugioh\Application\Presentation\MatchOptionProjection::build($fixtures, $presentationLabels, $deckMatchLabel, 'ygo_ui_match_label');

[$qualifierCompetition, $qualifierCompetitions, $pendingEventQualifiers, $activeQualifierCompetition, $requestedQualifierId, $requestedQualifierCompetition, $showQualifierDetail, $qualifierCompetitionIdMap] = \StationX\Yugioh\Application\Presentation\QualifierSelectionProjection::select($competitions, (int) ($_GET['event_id'] ?? 0), 'ygo_is_pending_event_qualifier_candidate', 'ygo_normalize_status_key');
$qualifierStatsByCompetition = \StationX\Yugioh\Application\Presentation\QualifierMatchProjection::statistics($fixtures, $qualifierCompetitionIdMap);
[$displayQualifierCompetition, $displayQualifierId, $displayQualifierSelectedIds, $displayQualifierSelectedMap, $displayQualifierDecks, $displayQualifierRequiredDuels, $displayQualifierMatchesPerDay] = \StationX\Yugioh\Application\Presentation\QualifierSelectionProjection::display($requestedQualifierCompetition, $activeQualifierCompetition, $qualifierCompetition, $allDecks);
[
    'matches' => $displayQualifierMatches,
    'roundCount' => $displayQualifierRoundCount,
    'scheduledCount' => $displayQualifierScheduledCount,
    'finishedCount' => $displayQualifierFinishedCount,
] = \StationX\Yugioh\Application\Presentation\QualifierMatchProjection::display($fixtures, $displayQualifierId);
[$qualifierStatus, $qualifierFinished, $qualifierStatusLabel, $divisionModeLabel] = \StationX\Yugioh\Application\Presentation\QualifierSelectionProjection::status($displayQualifierCompetition, 'ygo_normalize_status_key');
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
[$rulesetSelectOptions, $formatSelectOptions] = \StationX\Yugioh\Application\Presentation\DeckFormatProjection::options($rulesets, $eventRulesetOptions);
[$deckFormatCodesById, $allDecks] = \StationX\Yugioh\Application\Presentation\DeckFormatProjection::assign($allDecks, $formatSelectOptions, !empty($dbStatus['connected']) && isset($pdo) ? static fn(string $code): array => ygo_deck_ids_for_ruleset($pdo, $code) : null);
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
[$pendingConfigEventCount, $pendingEventCount, $completedEventCount, $cancelledEventCount, $activeGameRuleCount, $deckWithTeamCount, $deckWithoutTeamCount, $deckWithMediaCount, $eventsWithoutDateCount, $scheduledFixtureMatches, $nextDashboardEvent] = \StationX\Yugioh\Application\Presentation\DashboardSummaryProjection::build($competitions, $gameRules, $allDecks, $fixtures, $activeDashboardEvents, 'ygo_normalize_status_key');
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
