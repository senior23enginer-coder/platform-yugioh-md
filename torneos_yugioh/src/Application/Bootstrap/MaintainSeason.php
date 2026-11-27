<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final readonly class MaintainSeason
{
    public function __construct(private SeasonMaintenancePort $port) {}

    public function execute(int $seasonId, array $definitions, array $rulesetOptions): array
    {
        $this->port->ensureSettings();
        $this->port->ensureLabels();
        $deletedSeedDivisions = $this->port->removeSeededDivisions($seasonId, $definitions);
        $this->port->ensureRulesets($seasonId, $rulesetOptions);
        $this->port->ensureRulesetVersions($seasonId);
        $this->port->ensureMatchTypes();
        $this->port->ensureGameRules($seasonId);
        $goatLeagueStructure = $this->port->ensureGoatStructure($seasonId);
        $octoberScheduleMigration = $this->port->applyHistoricalSchedule();
        $recentDeckCalendarExpansion = $this->port->expandRecentLeagueCalendar();
        $activatedDueCompetitions = $this->port->activateDueCompetitions();
        $this->port->ensureCompetitionCodes($seasonId);
        $teamSyncCount = $this->port->syncDeckTeams();
        $deckFormatSyncCount = $this->port->normalizeDeckFormats();
        $deckAnalysisBaselineCount = $this->port->seedDeckAnalysis();
        return compact(
            'deletedSeedDivisions',
            'goatLeagueStructure',
            'octoberScheduleMigration',
            'recentDeckCalendarExpansion',
            'activatedDueCompetitions',
            'teamSyncCount',
            'deckFormatSyncCount',
            'deckAnalysisBaselineCount',
        );
    }
}

