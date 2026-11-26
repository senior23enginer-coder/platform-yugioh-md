<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class DashboardProjection
{
    public static function eventsByFormat(array $competitions, array $eventRulesetOptions): array
    {
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
        return $currentEventsByFormat;
    }

    public static function recentResults(array $fixtures, string $todayIso, array $presentationLabels, callable $matchLabel): array
    {
        $recentResults = [];
        foreach ($fixtures as $code => $rounds) {
            foreach ($rounds as $round) {
                foreach (($round['matches'] ?? []) as $match) {
                    if (($match['estado'] ?? '') !== 'FINALIZADA' && empty($match['score'])) {
                        continue;
                    }
                    $recentResults[] = [
                        'date' => (string) (($match['scheduled_on'] ?? '') ?: $todayIso),
                        'label' => $matchLabel($match, (string)$code, $round['ronda'] ?? '-', $presentationLabels),
                        'versus' => (string) (($match['a'] ?? 'Deck A') . ' vs ' . ($match['b'] ?? 'Deck B')),
                        'score' => (string) (($match['score'] ?? '') ?: '0-0'),
                        'status' => (string) (($match['estado'] ?? '') ?: 'FINALIZADA'),
                    ];
                }
            }
        }
        usort($recentResults, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
        $recentResults = array_slice($recentResults, 0, 6);
        return $recentResults;
    }

    public static function fixtureCounts(array $currentEventsByFormat, array $fixtures): array
    {
        $activeDashboardEvents = [];
        foreach ($currentEventsByFormat as $formatEvents) {
            foreach ($formatEvents as $event) {
                $activeDashboardEvents[] = $event;
            }
        }
        $activeEventCount = count($activeDashboardEvents);
        $totalFixtureMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count($round['matches'] ?? []), $rounds)), $fixtures));
        $totalFinalizedMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count(array_filter($round['matches'] ?? [], static fn(array $match): bool => (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']))), $rounds)), $fixtures));
        return [$activeDashboardEvents, $activeEventCount, $totalFixtureMatches, $totalFinalizedMatches];
    }

    public static function matches(array $fixtures, array $presentationLabels, callable $deckMatchLabel, callable $matchLabel, string $today): array
    {
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
                        'label' => $matchLabel($match, (string)$code, $round['ronda'] ?? '-', $presentationLabels),
                        'versus' => $deckMatchLabel((string) ($match['a'] ?? 'Deck A')) . ' vs ' . $deckMatchLabel((string) ($match['b'] ?? 'Deck B')),
                        'status' => (string) (($match['estado'] ?? '') ?: 'PENDIENTE'),
                        'score' => (string) (($match['score'] ?? '') ?: ''),
                        'competition_id' => $competitionId,
                    ];
                }
            }
        }
        usort($dashboardMatches, static fn(array $a, array $b): int => strcmp(($a['date'] ?: '2999-12-31') . ' ' . ($a['time'] ?: '23:59:59'), ($b['date'] ?: '2999-12-31') . ' ' . ($b['time'] ?: '23:59:59')));
        $upcomingDashboardMatches = array_slice(array_values(array_filter($dashboardMatches, static fn(array $match): bool => ($match['date'] ?? '') === $today)), 0, 8);
        return [$dashboardMatches, $dashboardMatchStatsByCompetition, $dashboardMatchStatsByPhase, $dashboardDeckUsage, $upcomingDashboardMatches];
    }

    public static function teamsAndRanking(array $allDecks, array $teams, array $activeDashboardEvents, callable $mediaSource): array
    {
        $teamDeckCounts = [];
        $teamDashboardLogos = [];
        foreach ($allDecks as $deck) {
            $teamName = (string) (($deck['team'] ?? '') ?: 'Sin equipo');
            $teamDeckCounts[$teamName] = ($teamDeckCounts[$teamName] ?? 0) + 1;
            $deckLogo = $mediaSource((string) (($deck['team_logo'] ?? '') ?: (($deck['cover_url'] ?? '') ?: ($deck['logo_url'] ?? ''))));
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
        $topDashboardTeamPool = array_map(static function (array $team) use ($teamDashboardLogos, $mediaSource): array {
            $teamName = (string) ($team['name'] ?? '');
            $team['logo_url'] = $mediaSource((string) (($team['logo_url'] ?? '') ?: ($teamDashboardLogos[$teamName] ?? '')));
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
        return [$teamDeckCounts, $teamDashboardLogos, $dashboardLiveRanking, $dashboardRankingSource, $topDashboardDecks, $topDashboardTeamPool, $topDashboardTeams];
    }
}
