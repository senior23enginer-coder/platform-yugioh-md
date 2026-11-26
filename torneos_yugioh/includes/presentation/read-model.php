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
$qualifierStatsByCompetition = [];
foreach ($fixtures as $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            if ($competitionId <= 0 || !isset($qualifierCompetitionIdMap[$competitionId])) {
                continue;
            }
            if (!isset($qualifierStatsByCompetition[$competitionId])) {
                $qualifierStatsByCompetition[$competitionId] = ['matches' => 0, 'finished' => 0];
            }
            $qualifierStatsByCompetition[$competitionId]['matches']++;
            if ((string) ($match['estado'] ?? '') === 'FINALIZADA' || !empty($match['score'])) {
                $qualifierStatsByCompetition[$competitionId]['finished']++;
            }
        }
    }
}
$displayQualifierCompetition = $requestedQualifierCompetition ?? $activeQualifierCompetition ?? $qualifierCompetition;
$displayQualifierId = (int) ($displayQualifierCompetition['id'] ?? 0);
$displayQualifierSelectedIds = array_map('intval', (array) ($displayQualifierCompetition['selected_deck_ids'] ?? []));
$displayQualifierSelectedMap = array_fill_keys($displayQualifierSelectedIds, true);
$displayQualifierDecks = $displayQualifierSelectedIds
    ? array_values(array_filter($allDecks, fn(array $deck): bool => isset($displayQualifierSelectedMap[(int) ($deck['id'] ?? 0)])))
    : [];
$displayQualifierRequiredDuels = max(2, min(5, (int) (($displayQualifierCompetition['qualifier_duels_per_deck'] ?? 0) ?: 3)));
$displayQualifierMatchesPerDay = max(1, min(5, (int) (($displayQualifierCompetition['qualifier_matches_per_day'] ?? 0) ?: 1)));
$displayQualifierMatches = [];
foreach (($fixtures['CLASIFICATORIO'] ?? []) as $round) {
    foreach (($round['matches'] ?? []) as $match) {
        if ($displayQualifierId > 0 && (int) ($match['competition_id'] ?? 0) !== $displayQualifierId) {
            continue;
        }
        $displayQualifierMatches[] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
    }
}
usort($displayQualifierMatches, static function (array $a, array $b): int {
    return strcmp((string) ($a['scheduled_on'] ?? '9999-12-31'), (string) ($b['scheduled_on'] ?? '9999-12-31'))
        ?: strcmp((string) ($a['starts_at'] ?? '23:59:59'), (string) ($b['starts_at'] ?? '23:59:59'))
        ?: ((int) ($a['round_number'] ?? 0) <=> (int) ($b['round_number'] ?? 0))
        ?: ((int) ($a['mesa'] ?? 0) <=> (int) ($b['mesa'] ?? 0));
});
$displayQualifierRoundCount = count(array_unique(array_map(static fn(array $match): int => (int) ($match['round_number'] ?? 0), $displayQualifierMatches)));
$displayQualifierScheduledCount = count(array_filter($displayQualifierMatches, static fn(array $match): bool => !empty($match['scheduled_on'])));
$displayQualifierFinishedCount = count(array_filter($displayQualifierMatches, static fn(array $match): bool => (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score'])));
$qualifierStatus = ygo_normalize_status_key((string) ($displayQualifierCompetition['status'] ?? 'draft'));
$qualifierFinished = in_array($qualifierStatus, ['completed', 'finalizada', 'finalizado', 'closed', 'archived'], true);
$qualifierStatusLabel = $qualifierFinished ? 'Finalizado' : ($qualifierStatus === 'pending_event' ? 'Pendiente evento' : (in_array($qualifierStatus, ['active', 'activo', 'en_curso'], true) ? 'Activo' : 'Pendiente configuracion'));
$divisionModeLabel = $qualifierFinished ? 'Formatos oficiales' : 'Formatos pendientes del clasificatorio';
$cupEventTypes = ['cup', 'final'];
$cupCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => in_array((string) ($competition['type'] ?? ''), $cupEventTypes, true)));
$cupMatchesByCompetition = [];
$cupStatsByCompetition = [];
foreach ($fixtures as $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            if ($competitionId <= 0) {
                continue;
            }
            $phase = (string) (($match['phase'] ?? '') ?: '');
            if (!in_array($phase, ['cup', 'final'], true)) {
                continue;
            }
            $cupMatchesByCompetition[$competitionId][] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
            if (!isset($cupStatsByCompetition[$competitionId])) {
                $cupStatsByCompetition[$competitionId] = ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0, 'dates' => []];
            }
            $isFinalized = (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']);
            $hasDate = !empty($match['scheduled_on']);
            $cupStatsByCompetition[$competitionId]['total']++;
            $cupStatsByCompetition[$competitionId][$isFinalized ? 'finalized' : 'pending']++;
            if ($hasDate) {
                $cupStatsByCompetition[$competitionId]['scheduled']++;
                $cupStatsByCompetition[$competitionId]['dates'][(string) $match['scheduled_on']] = true;
            }
        }
    }
}
foreach ($cupMatchesByCompetition as &$cupMatches) {
    usort($cupMatches, static fn(array $left, array $right): int =>
        ((int) ($left['display_order'] ?? 0) <=> (int) ($right['display_order'] ?? 0))
        ?: ((int) ($left['round_number'] ?? 0) <=> (int) ($right['round_number'] ?? 0))
        ?: ((int) ($left['mesa'] ?? 0) <=> (int) ($right['mesa'] ?? 0))
    );
}
unset($cupMatches);
$cupActiveCount = count(array_filter($cupCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['active', 'activo', 'en_curso'], true)));
$cupCompletedCount = count(array_filter($cupCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['completed', 'finalized', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'], true)));
$cupTotalMatches = array_sum(array_map(static fn(array $stats): int => (int) ($stats['total'] ?? 0), $cupStatsByCompetition));
$featuredCupCompetition = $cupCompetitions[0] ?? null;
$worldCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => (string) ($competition['type'] ?? '') === 'world'));
$worldMatchesByCompetition = [];
$worldStatsByCompetition = [];
foreach ($fixtures as $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            if ($competitionId <= 0 || (string) (($match['phase'] ?? '') ?: '') !== 'world') {
                continue;
            }
            $worldMatchesByCompetition[$competitionId][] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
            if (!isset($worldStatsByCompetition[$competitionId])) {
                $worldStatsByCompetition[$competitionId] = ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0];
            }
            $isFinalized = (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']);
            $worldStatsByCompetition[$competitionId]['total']++;
            $worldStatsByCompetition[$competitionId][$isFinalized ? 'finalized' : 'pending']++;
            if (!empty($match['scheduled_on'])) {
                $worldStatsByCompetition[$competitionId]['scheduled']++;
            }
        }
    }
}
$worldActiveCount = count(array_filter($worldCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['active', 'activo', 'en_curso'], true)));
$worldTotalMatches = array_sum(array_map(static fn(array $stats): int => (int) ($stats['total'] ?? 0), $worldStatsByCompetition));
$eventSummaryByType = [];
$eventSummaryByRuleset = [];
$eventSummaryByStatus = [];
foreach ($competitions as $competition) {
    $type = (string) (($competition['type'] ?? '') ?: 'special');
    $ruleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
    $statusKey = (string) (($competition['status'] ?? '') ?: 'draft');
    $eventSummaryByType[$type] = ($eventSummaryByType[$type] ?? 0) + 1;
    $eventSummaryByRuleset[$ruleset] = ($eventSummaryByRuleset[$ruleset] ?? 0) + 1;
    $eventSummaryByStatus[$statusKey] = ($eventSummaryByStatus[$statusKey] ?? 0) + 1;
}
$competitionById = [];
foreach ($competitions as $competition) {
    $competitionById[(int) ($competition['id'] ?? 0)] = $competition;
}
$dependencyFlow = [];
foreach ($competitions as $competition) {
    foreach (($competition['dependencies'] ?? []) as $dependency) {
        $dependencyFlow[] = [
            'from' => (string) ($dependency['name'] ?? 'Evento previo'),
            'to' => (string) ($competition['name'] ?? 'Evento'),
            'ruleset' => (string) (($competition['event_ruleset'] ?? '') ?: 'custom'),
            'ok' => !empty($dependency['ok']),
            'status' => (string) ($dependency['status'] ?? 'draft'),
            'condition' => (string) ($dependency['condition_type'] ?? 'completed_with_results'),
        ];
    }
}
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
$currentEventsByFormat = [];
foreach ($competitions as $competition) {
    $status = strtolower((string) ($competition['status'] ?? 'draft'));
    $starts = (string) ($competition['starts_on'] ?? '');
    $ends = (string) ($competition['ends_on'] ?? '');
    if (!in_array($status, ['active', 'en_curso', 'activo'], true)) {
        continue;
    }
    $formatKey = (string) (($competition['division_code'] ?? '') ?: (($eventRulesetOptions[(string) ($competition['event_ruleset'] ?? '')] ?? '') ?: 'General'));
    $currentEventsByFormat[$formatKey][] = $competition;
}
$recentResults = [];
foreach ($fixtures as $code => $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            if (($match['estado'] ?? '') !== 'FINALIZADA' && empty($match['score'])) {
                continue;
            }
            $recentResults[] = [
                'date' => (string) (($match['scheduled_on'] ?? '') ?: $todayIso),
                'label' => ygo_ui_match_label($match, (string)$code, $round['ronda'] ?? '-', $presentationLabels),
                'versus' => (string) (($match['a'] ?? 'Deck A') . ' vs ' . ($match['b'] ?? 'Deck B')),
                'score' => (string) (($match['score'] ?? '') ?: '0-0'),
                'status' => (string) (($match['estado'] ?? '') ?: 'FINALIZADA'),
            ];
        }
    }
}
usort($recentResults, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
$recentResults = array_slice($recentResults, 0, 6);
$activeDashboardEvents = [];
foreach ($currentEventsByFormat as $formatEvents) {
    foreach ($formatEvents as $event) {
        $activeDashboardEvents[] = $event;
    }
}
$activeEventCount = count($activeDashboardEvents);
$totalFixtureMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count($round['matches'] ?? []), $rounds)), $fixtures));
$totalFinalizedMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count(array_filter($round['matches'] ?? [], static fn(array $match): bool => (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']))), $rounds)), $fixtures));
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
$dashboardMatches = [];
$dashboardMatchStatsByCompetition = [];
$dashboardMatchStatsByPhase = [];
$dashboardDeckUsage = [];
foreach ($fixtures as $code => $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            $phaseKey = (string) (($match['phase'] ?? '') ?: strtolower((string) $code));
            $isFinalized = (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']);
            if (!isset($dashboardMatchStatsByPhase[$phaseKey])) {
                $dashboardMatchStatsByPhase[$phaseKey] = ['total' => 0, 'finalized' => 0, 'pending' => 0];
            }
            $dashboardMatchStatsByPhase[$phaseKey]['total']++;
            $dashboardMatchStatsByPhase[$phaseKey][$isFinalized ? 'finalized' : 'pending']++;
            if ($competitionId > 0) {
                if (!isset($dashboardMatchStatsByCompetition[$competitionId])) {
                    $dashboardMatchStatsByCompetition[$competitionId] = ['total' => 0, 'finalized' => 0, 'pending' => 0];
                }
                $dashboardMatchStatsByCompetition[$competitionId]['total']++;
                $dashboardMatchStatsByCompetition[$competitionId][$isFinalized ? 'finalized' : 'pending']++;
            }
            foreach (['a', 'b'] as $side) {
                $deckName = (string) ($match[$side] ?? '');
                if ($deckName !== '') {
                    $dashboardDeckUsage[$deckName] = ($dashboardDeckUsage[$deckName] ?? 0) + 1;
                }
            }
            $dashboardMatches[] = [
                'id' => (int) ($match['id'] ?? 0),
                'date' => (string) (($match['scheduled_on'] ?? '') ?: ''),
                'time' => (string) (($match['starts_at'] ?? '') ?: ''),
                'label' => ygo_ui_match_label($match, (string)$code, $round['ronda'] ?? '-', $presentationLabels),
                'versus' => $deckMatchLabel((string) ($match['a'] ?? 'Deck A')) . ' vs ' . $deckMatchLabel((string) ($match['b'] ?? 'Deck B')),
                'status' => (string) (($match['estado'] ?? '') ?: 'PENDIENTE'),
                'score' => (string) (($match['score'] ?? '') ?: ''),
                'competition_id' => $competitionId,
            ];
        }
    }
}
usort($dashboardMatches, static fn(array $a, array $b): int => strcmp(($a['date'] ?: '2999-12-31') . ' ' . ($a['time'] ?: '23:59:59'), ($b['date'] ?: '2999-12-31') . ' ' . ($b['time'] ?: '23:59:59')));
$upcomingDashboardMatches = array_slice(array_values(array_filter($dashboardMatches, static fn(array $match): bool => ($match['date'] ?? '') === date('Y-m-d'))), 0, 8);
$teamDeckCounts = [];
$teamDashboardLogos = [];
foreach ($allDecks as $deck) {
    $teamName = (string) (($deck['team'] ?? '') ?: 'Sin equipo');
    $teamDeckCounts[$teamName] = ($teamDeckCounts[$teamName] ?? 0) + 1;
    $deckLogo = ygo_media_src((string) (($deck['team_logo'] ?? '') ?: (($deck['cover_url'] ?? '') ?: ($deck['logo_url'] ?? ''))));
    if ($deckLogo !== '' && empty($teamDashboardLogos[$teamName])) {
        $teamDashboardLogos[$teamName] = $deckLogo;
    }
}
$dashboardLiveRanking = [];
$dashboardRankingSource = '';
foreach ($activeDashboardEvents as $event) {
    $ranking = array_values(array_filter((array) ($event['ranking'] ?? []), static fn(array $row): bool => (int) ($row['pj'] ?? 0) > 0));
    if ($ranking) {
        $dashboardLiveRanking = array_slice($ranking, 0, 8);
        $dashboardRankingSource = (string) ($event['name'] ?? 'Evento activo');
        break;
    }
}
$topDashboardDecks = array_slice($allDecks, 0, 10);
$topDashboardTeamPool = array_map(static function (array $team) use ($teamDashboardLogos): array {
    $teamName = (string) ($team['name'] ?? '');
    $team['logo_url'] = ygo_media_src((string) (($team['logo_url'] ?? '') ?: ($teamDashboardLogos[$teamName] ?? '')));
    return $team;
}, $teams);
usort($topDashboardTeamPool, static function (array $a, array $b) use ($teamDeckCounts): int {
    $aLogo = trim((string) ($a['logo_url'] ?? '')) !== '' ? 1 : 0;
    $bLogo = trim((string) ($b['logo_url'] ?? '')) !== '' ? 1 : 0;
    $aDecks = (int) ($teamDeckCounts[(string) ($a['name'] ?? '')] ?? 0);
    $bDecks = (int) ($teamDeckCounts[(string) ($b['name'] ?? '')] ?? 0);
    return $bLogo <=> $aLogo ?: $bDecks <=> $aDecks ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});
$topDashboardTeams = array_slice($topDashboardTeamPool, 0, 8);
$qualifierPairings = [];
$deckCount = count($displayQualifierDecks);
if ($deckCount > 1) {
    foreach ($displayQualifierDecks as $index => $deck) {
        $rivals = [];
        for ($offset = 1; $offset <= $displayQualifierRequiredDuels; $offset++) {
            $rival = $displayQualifierDecks[($index + $offset) % $deckCount];
            if ($rival['name'] !== $deck['name']) {
                $rivals[] = $rival['name'];
            }
        }
        $qualifierPairings[] = ['deck' => $deck['name'], 'team' => $deck['team'] ?? 'Sin equipo', 'rivals' => array_slice(array_values(array_unique($rivals)), 0, $displayQualifierRequiredDuels)];
    }
}
$qualifierProgress = [];
$displayQualifierRanking = array_values((array) ($displayQualifierCompetition['ranking'] ?? []));
$displayQualifierRankingByDeckId = [];
foreach ($displayQualifierRanking as $position => $standingRow) {
    $displayQualifierRankingByDeckId[(int) ($standingRow['id'] ?? 0)] = $standingRow + ['position' => $position + 1];
}
foreach ($displayQualifierDecks as $deck) {
    $deckId = (int) ($deck['id'] ?? 0);
    $standing = $displayQualifierRankingByDeckId[$deckId] ?? [];
    $qualifierProgress[$deck['name']] = [
        'rank' => (int) ($standing['position'] ?? $deck['rank']),
        'seed_rank' => (int) ($deck['rank'] ?? 0),
        'deck_id' => $deckId,
        'deck' => $deck['name'],
        'team' => $deck['team'] ?? 'Sin equipo',
        'code' => $deck['team_code'] ?? '',
        'team_logo' => ygo_media_src((string) ($deck['team_logo'] ?? '')),
        'division' => $deck['division'],
        'pts' => (int) ($standing['pts'] ?? 0),
        'pj' => (int) ($standing['pj'] ?? 0),
        'pg' => (int) ($standing['pg'] ?? 0),
        'pe' => (int) ($standing['pe'] ?? 0),
        'pp' => (int) ($standing['pp'] ?? 0),
        'cg' => (int) ($standing['cg'] ?? 0),
        'cp' => (int) ($standing['cp'] ?? 0),
        'dp' => (int) ($standing['dp'] ?? 0),
        'played' => 0,
        'scheduled' => 0,
        'opponents' => [],
        'ready' => false,
    ];
}
foreach (($fixtures['CLASIFICATORIO'] ?? []) as $round) {
    foreach (($round['matches'] ?? []) as $match) {
        if ($displayQualifierId > 0 && (int) ($match['competition_id'] ?? 0) !== $displayQualifierId) {
            continue;
        }
        foreach ([[$match['a'] ?? '', $match['b'] ?? ''], [$match['b'] ?? '', $match['a'] ?? '']] as $pair) {
            [$deckName, $opponentName] = $pair;
            if (!isset($qualifierProgress[$deckName])) {
                continue;
            }
            if (!empty($match['scheduled_on'])) {
                $qualifierProgress[$deckName]['scheduled']++;
            }
            if (($match['estado'] ?? '') === 'FINALIZADA') {
                $qualifierProgress[$deckName]['played']++;
                if ($opponentName !== '') {
                    $qualifierProgress[$deckName]['opponents'][$opponentName] = true;
                }
            }
        }
    }
}
foreach ($qualifierProgress as &$progressRow) {
    $progressRow['opponent_count'] = count($progressRow['opponents']);
    $progressRow['ready'] = $progressRow['played'] >= $displayQualifierRequiredDuels;
    $progressRow['remaining'] = max(0, $displayQualifierRequiredDuels - $progressRow['played']);
}
unset($progressRow);
usort($qualifierProgress, static fn(array $a, array $b): int =>
    $b['pts'] <=> $a['pts']
    ?: $b['dp'] <=> $a['dp']
    ?: $b['cg'] <=> $a['cg']
    ?: $a['seed_rank'] <=> $b['seed_rank']
);
foreach ($qualifierProgress as $position => &$progressRow) {
    $progressRow['rank'] = $position + 1;
}
unset($progressRow);
$qualifierReadyCount = count(array_filter($qualifierProgress, static fn(array $row): bool => $row['ready']));
$qualifierFinalizeCheck = ['ok' => false, 'reasons' => ['Conecta la base de datos para validar el cierre.']];
if ($dbStatus['connected'] && isset($pdo)) {
    try {
        $qualifierFinalizeCheck = ygo_qualifier_finalize_check($pdo, $displayQualifierId);
    } catch (Throwable $error) {
        $qualifierFinalizeCheck = ['ok' => false, 'reasons' => ['No se pudo validar el cierre del clasificatorio.']];
    }
}
$auditSummary = [];
foreach ($audit ?? [] as $auditRow) {
    $action = (string) ($auditRow['action'] ?? 'accion');
    $auditSummary[$action] = ($auditSummary[$action] ?? 0) + 1;
}
arsort($auditSummary);

$duelPlayerSide = strtoupper((string) ($_GET['duel_player'] ?? ''));
