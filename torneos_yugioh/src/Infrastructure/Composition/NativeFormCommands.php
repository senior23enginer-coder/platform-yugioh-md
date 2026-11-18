<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use PDO;
use StationX\Yugioh\Application\Commands\DispatchFormCommand;

/** Explicit compatibility composition. Constructors perform no database work. */
final class NativeFormCommands
{
    public static function early(PDO $pdo): DispatchFormCommand
    {
        return new DispatchFormCommand([
            'configure_statistics_global' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ConfigureStatisticsGlobalHandler(
                $pdo,
            ),
            'publish_statistics_cut' => new \StationX\Yugioh\Infrastructure\Http\FormActions\PublishStatisticsCutHandler(
                $pdo,
            ),
        ]);
    }

    public static function regular(PDO $pdo): DispatchFormCommand
    {
        return new DispatchFormCommand([
            'sync_offline_form' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SyncOfflineFormHandler(
                $pdo,
            ),
            'save_settings' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveSettingsHandler(
                $pdo,
            ),
            'save_ui_labels' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveUiLabelsHandler(
                $pdo,
            ),
            'save_catalog_items' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveCatalogItemsHandler(
                $pdo,
            ),
            'save_team' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveTeamHandler(
                $pdo,
            ),
            'save_player' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SavePlayerHandler(
                $pdo,
            ),
            'select_logo_history' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SelectLogoHistoryHandler(
                $pdo,
            ),
            'save_team_deck_links' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveTeamDeckLinksHandler(
                $pdo,
            ),
            'save_division' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveDivisionHandler(
                $pdo,
            ),
            'save_match_schedule' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveMatchScheduleHandler(
                $pdo,
            ),
            'generate_friendlies' => new \StationX\Yugioh\Infrastructure\Http\FormActions\GenerateFriendliesHandler(
                $pdo,
            ),
            'generate_leagues' => new \StationX\Yugioh\Infrastructure\Http\FormActions\GenerateLeaguesHandler(
                $pdo,
            ),
            'generate_cups' => new \StationX\Yugioh\Infrastructure\Http\FormActions\GenerateCupsHandler(
                $pdo,
            ),
            'generate_world' => new \StationX\Yugioh\Infrastructure\Http\FormActions\GenerateWorldHandler(
                $pdo,
            ),
            'auto_schedule_qualifier' => new \StationX\Yugioh\Infrastructure\Http\FormActions\AutoScheduleQualifierHandler(
                $pdo,
            ),
            'rebuild_qualifier_calendar' => new \StationX\Yugioh\Infrastructure\Http\FormActions\RebuildQualifierCalendarHandler(
                $pdo,
            ),
            'finalize_ffa' => new \StationX\Yugioh\Infrastructure\Http\FormActions\FinalizeFfaHandler(
                $pdo,
            ),
            'create_ffa_match' => new \StationX\Yugioh\Infrastructure\Http\FormActions\CreateFfaMatchHandler(
                $pdo,
            ),
            'save_ffa_action' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveFfaActionHandler(
                $pdo,
            ),
            'save_team_lineups' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveTeamLineupsHandler(
                $pdo,
            ),
            'save_team_result' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveTeamResultHandler(
                $pdo,
            ),
            'freeze_event_card_rules' => new \StationX\Yugioh\Infrastructure\Http\FormActions\FreezeEventCardRulesHandler(
                $pdo,
            ),
            'save_event_card_rules' => new \StationX\Yugioh\Infrastructure\Http\FormActions\FreezeEventCardRulesHandler(
                $pdo,
            ),
            'save_event_admission' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveEventAdmissionHandler(
                $pdo,
            ),
            'configure_competition' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ConfigureCompetitionHandler(
                $pdo,
            ),
            'finalize_competition' => new \StationX\Yugioh\Infrastructure\Http\FormActions\FinalizeCompetitionHandler(
                $pdo,
            ),
            'revert_event_stage' => new \StationX\Yugioh\Infrastructure\Http\FormActions\RevertEventStageHandler(
                $pdo,
            ),
            'advance_competition' => new \StationX\Yugioh\Infrastructure\Http\FormActions\AdvanceCompetitionHandler(
                $pdo,
            ),
            'apply_qualifier_divisions' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ApplyQualifierDivisionsHandler(
                $pdo,
            ),
            'apply_league_movements' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ApplyLeagueMovementsHandler(
                $pdo,
            ),
            'recalculate_competition_standings' => new \StationX\Yugioh\Infrastructure\Http\FormActions\RecalculateCompetitionStandingsHandler(
                $pdo,
            ),
            'archive_qa_codex_data' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ArchiveQaCodexDataHandler(
                $pdo,
            ),
            'delete_competition' => new \StationX\Yugioh\Infrastructure\Http\FormActions\DeleteCompetitionHandler(
                $pdo,
            ),
            'delete_manual_friendly' => new \StationX\Yugioh\Infrastructure\Http\FormActions\DeleteManualFriendlyHandler(
                $pdo,
            ),
            'advance_phase' => new \StationX\Yugioh\Infrastructure\Http\FormActions\AdvancePhaseHandler(
                $pdo,
            ),
            'create_match' => new \StationX\Yugioh\Infrastructure\Http\FormActions\CreateMatchHandler(
                $pdo,
            ),
            'save_deck_card' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveDeckCardHandler(
                $pdo,
            ),
            'import_deck_cards' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ImportDeckCardsHandler(
                $pdo,
            ),
            'save_deck_media' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveDeckMediaHandler(
                $pdo,
            ),
            'save_honor' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveHonorHandler(
                $pdo,
            ),
            'save_checkin' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveCheckinHandler(
                $pdo,
            ),
            'save_match_result' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveMatchResultHandler(
                $pdo,
            ),
            'save_deck_analysis_profile' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveDeckAnalysisProfileHandler(
                $pdo,
            ),
            'save_matchup_analysis' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveMatchupAnalysisHandler(
                $pdo,
            ),
            'save_deck' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveDeckHandler(
                $pdo,
            ),
            'remove_deck' => new \StationX\Yugioh\Infrastructure\Http\FormActions\RemoveDeckHandler(
                $pdo,
            ),
            'save_competition' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveCompetitionHandler(
                $pdo,
            ),
            'save_ruleset' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveRulesetHandler(
                $pdo,
            ),
            'save_game_rule' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveGameRuleHandler(
                $pdo,
            ),
            'archive_game_rule' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ArchiveGameRuleHandler(
                $pdo,
            ),
            'archive_competition' => new \StationX\Yugioh\Infrastructure\Http\FormActions\ArchiveCompetitionHandler(
                $pdo,
            ),
            'save_competition_dependency' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SaveCompetitionDependencyHandler(
                $pdo,
            ),
            'delete_competition_dependency' => new \StationX\Yugioh\Infrastructure\Http\FormActions\DeleteCompetitionDependencyHandler(
                $pdo,
            ),
            'set_qualifier_status' => new \StationX\Yugioh\Infrastructure\Http\FormActions\SetQualifierStatusHandler(
                $pdo,
            ),
        ]);
    }
}
