<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy;

final readonly class ConfigureCompetition
{
    public function __construct(
        private CompetitionConfigurationStore $store,
        private CompetitionConfigurationContext $context,
        private CompetitionConfigurationRoster $roster,
        private CompetitionConfigurationMedia $media,
        private CompetitionConfigurationSchedule $schedule,
        private TransactionBoundary $transaction,
        private Clock $clock,
    ) {}

    public function execute(array $input, array $typeOptions): int
    {
        $competitionId = (int) ($input['competition_id'] ?? 0);
        if ($competitionId <= 0) {
            return 0;
        }
        $seasonId = $this->context->currentSeasonId();
        $competition = $this->store->competition($seasonId, $competitionId);
        if (!$competition) {
            return 0;
        }

        $auditBefore = $this->context->auditSnapshot($competitionId);
        $mode = (string) ($input['config_mode'] ?? 'save');
        if(in_array($mode,['start','schedule_qualifier','schedule_cup','save_schedule'],true)){
            if ($this->store->matchCount($competitionId) === 0) $this->context->requireSupportedModel($this->context->teamContext($competition));
            elseif(in_array($mode,['schedule_qualifier','schedule_cup','save_schedule'],true)) $this->context->assertFixtureMutable($competitionId);
        }
        if (!in_array($mode, ['save', 'save_schedule', 'start', 'schedule_qualifier', 'schedule_cup'], true)) {
            $mode = 'save';
        }
        $competitionName = trim((string) ($input['competition_name'] ?? ($competition['name'] ?? '')));
        $competitionType = trim((string) ($input['competition_type'] ?? ($competition['type'] ?? 'special')));
        if (!empty($competition['type'])) {
            $competitionType = (string) $competition['type'];
        }
        if (!array_key_exists($competitionType, $typeOptions)) {
            $competitionType = (string) ($competition['type'] ?? 'special');
        }
        $divisionId = !empty($competition['division_id']) ? (int)$competition['division_id'] : null;
        $ownedDivisions = $this->roster->divisions($competitionId);
        $postedDivisionIds = array_map(static fn($division)=>(int)$division['id'], $ownedDivisions);
        if ($competitionType === 'league') {
            if (!$postedDivisionIds) throw new InvalidArgumentException('Asocia al menos una division a esta liga desde Divisiones.');
            if (!in_array($divisionId, $postedDivisionIds, true)) $divisionId = $postedDivisionIds[0];
        }
        $deckPoolMode = (string) ($input['competition_deck_pool'] ?? ($competition['deck_pool'] ?? 'all_active'));
        if (!in_array($deckPoolMode, ['all_active', 'division'], true)) {
            $deckPoolMode = 'all_active';
        }
        $leaguePromotionSlots = max(0, (int) ($input['competition_promotion_slots'] ?? ($competition['promotion_slots'] ?? 0)));
        $leagueRelegationSlots = max(0, (int) ($input['competition_relegation_slots'] ?? ($competition['relegation_slots'] ?? 0)));
        $leaguePromotionTo = ($input['competition_promotion_to'] ?? '') === '' ? null : (int) $input['competition_promotion_to'];
        $leagueRelegationTo = ($input['competition_relegation_to'] ?? '') === '' ? null : (int) $input['competition_relegation_to'];
        $lockDivisionMoves = isset($input['competition_lock_division_moves']) ? 1 : 0;
        if ($competitionType !== 'league' || count($postedDivisionIds) < 2) {
            $leaguePromotionSlots = 0;
            $leagueRelegationSlots = 0;
            $leaguePromotionTo = null;
            $leagueRelegationTo = null;
            $lockDivisionMoves = 0;
        }
        $eventRuleset = trim((string) ($input['competition_event_ruleset'] ?? ($competition['event_ruleset'] ?? 'custom')));
        if (!$this->store->hasRuleset($seasonId, $eventRuleset)) {
            $eventRuleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
        }
        $rulesetVersion = trim((string) ($input['competition_ruleset_version'] ?? ($competition['ruleset_version'] ?? '')));
        $accent = trim((string) ($input['competition_accent'] ?? ($competition['accent'] ?? '#36d8ff')));
        $rules = trim((string) ($input['competition_rules'] ?? ($competition['rules'] ?? '')));
        $rawPostedStatus = trim((string) ($input['competition_status'] ?? ($competition['status'] ?? 'pending_configuration')));
        $currentStatusKey = CompetitionLifecyclePolicy::normalizeStatus((string) ($competition['status'] ?? ''));
        $postedStatus = in_array($rawPostedStatus, ['cancelled', 'resume'], true)
            ? $rawPostedStatus
            : (string) (($competition['status'] ?? '') ?: 'pending_configuration');
        if (in_array($currentStatusKey, ['cancelled', 'canceled', 'anulado', 'anulada'], true)) {
            $nextStatus = in_array($postedStatus, ['resume', 'pending_configuration'], true) ? 'pending_configuration' : 'cancelled';
            $this->store->resumeCancelled($seasonId, $competitionId, $nextStatus);
            $this->context->recordHistory($seasonId, $competitionId, (string) ($competition['status'] ?? ''), $nextStatus, 'configure_competition', ['mode' => $mode, 'resume_from_cancelled' => $nextStatus === 'pending_configuration']);
            $this->store->audit($competitionId, ['mode' => $mode, 'status' => $nextStatus, 'resume_from_cancelled' => $nextStatus === 'pending_configuration', 'before'=>$auditBefore, 'after'=>$this->context->auditSnapshot($competitionId)]);
            return 0;
        }
        $qualifierDuelsPerDeck = max(2, min(5, (int) ($input['qualifier_duels_per_deck'] ?? ($competition['qualifier_duels_per_deck'] ?? 3))));

        $selectedDeckIds = $input['competition_deck_ids'] ?? [];
        if (!is_array($selectedDeckIds)) {
            $selectedDeckIds = [];
        }
        $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
        $currentCompetitionDeckIds = $this->store->roster($competitionId);
        $hasFixtures = $this->store->matchCount($competitionId) > 0;
        $deckRosterLocked = \StationX\Yugioh\Domain\Competitions\CompetitionConfigurationPolicy::rosterLocked(
            $hasFixtures,
            $competition,
            $currentStatusKey,
            $competitionType,
        );
        if ($deckRosterLocked) {
            $oldIds=$currentCompetitionDeckIds;$newIds=$selectedDeckIds;sort($oldIds);sort($newIds);
            if($oldIds!==$newIds) throw new InvalidArgumentException('No se pueden cambiar participantes de un evento ya programado.');
            $selectedDeckIds = $currentCompetitionDeckIds;
        } elseif ($deckPoolMode === 'division') {
            $selectedDeckIds = $this->roster->availableLeagueDeckIds($competitionId, $this->roster->formatDeckIds($eventRuleset));
        }
        $selectedDeckIds = $this->roster->validateFormatRoster($eventRuleset, $selectedDeckIds);
        if($this->roster->admissionLoad($competition)!==null)$selectedDeckIds=$this->roster->admissionRequire($competition,$selectedDeckIds,false);
        $today = $this->clock->now()->setTime(0, 0);
        $startDateRaw = (string) ($input['competition_start_on'] ?? '');
        $existingStartDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($competition['starts_on'] ?? '')) ? (string) $competition['starts_on'] : '';
        $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateRaw) ? $startDateRaw : ($existingStartDate ?: $today->format('Y-m-d'));
        if (new DateTimeImmutable($startDate) < $today) {
            $startDate = $existingStartDate !== '' ? $existingStartDate : $today->format('Y-m-d');
        }
        $pattern = (string) ($input['competition_schedule_pattern'] ?? 'consecutive');
        $allowedPatterns = ['consecutive', 'every_other_day', 'every_2_days', 'every_3_days', 'weekend'];
        if (!in_array($pattern, $allowedPatterns, true)) {
            $pattern = 'consecutive';
        }
        $qualifierMatchesPerDay = max(1, min(5, (int) ($input['qualifier_matches_per_day'] ?? ($competition['qualifier_matches_per_day'] ?? 1))));
        $matchFormat = (string) ($input['competition_match_format'] ?? ($competition['match_format'] ?? '1v1'));
        if(($competition['execution_model']??'')==='free_for_all'&&$matchFormat!=='ffa')throw new InvalidArgumentException('Esta ficha usa jugadores independientes, no lados1v1/2v2/3v3.');
        if($this->context->templateBaseCode((string)($competition['prompt_template']??''))==='pegasus_challenge'&&(!in_array($eventRuleset,['tcg_advanced','tcg_traditional'],true)||$matchFormat!=='1v1'))throw new InvalidArgumentException('Pegasus requiere modalidad individual y TCG Avanzado o Tradicional.');
        if (!in_array($matchFormat, ['1v1', '2v2', '3v3', 'ffa'], true)) {
            $matchFormat = '1v1';
        }
        $existingRuleCode = '';
        if (!empty($competition['game_rule_id'])) {
            $existingRuleCode = $this->store->ruleCode((int) $competition['game_rule_id']);
        }
        $fallbackRuleId = (int) ($competition['game_rule_id'] ?? 0) ?: null;
        $gameRuleId = $this->context->validGameRuleId($seasonId, (int) ($input['competition_game_rule_id'] ?? 0), $fallbackRuleId, $competitionType, (string) ($competition['execution_model'] ?? 'manual'));
        if((int)($competition['best_of']??3)===1 && empty($input['competition_game_rule_id']) && empty($competition['game_rule_id'])) $gameRuleId=null;
        $uploadedLogo = $this->media->upload();
        $galleryLogo = $this->media->validGalleryLogo((string) ($input['competition_gallery_logo'] ?? ''), $competitionType);
        $removeLogo = isset($input['competition_remove_logo']);
        $defaultLogos = $this->media->gallery()[$competitionType] ?? [];
        $defaultLogo = (string) ($defaultLogos[0]['url'] ?? '');
        $logoUrl = $removeLogo ? $defaultLogo : ($uploadedLogo !== '' ? $uploadedLogo : ($galleryLogo !== '' ? $galleryLogo : ((string) ($competition['logo_url'] ?? '') ?: $defaultLogo)));
        $participantsPerSide = max(1, min(3, (int) substr($matchFormat, 0, 1)));
        $intervalDays = ['consecutive' => 1, 'every_other_day' => 2, 'every_2_days' => 3, 'every_3_days' => 4, 'weekend' => 0][$pattern];
        $selectedDeckCount = count($selectedDeckIds);
        $savedTeamRoster=$this->roster->teamKind($competition)!==''?$this->roster->teamLoad($competition):null;
        $rosterGroups = $savedTeamRoster!==null ? array_column($savedTeamRoster['lineups'],'decks') : $this->roster->rosterGroups($selectedDeckIds, $participantsPerSide);
        $readiness = \StationX\Yugioh\Domain\Competitions\CompetitionConfigurationPolicy::readiness(
            $competitionType,
            $rosterGroups,
            $selectedDeckCount,
            $participantsPerSide,
            $qualifierDuelsPerDeck,
            $divisionId,
        );
        $qualifierDuelsPerDeck = $readiness['qualifierDuelsPerDeck'];
        $canRunCompetition = $readiness['canRunCompetition'];
        $expectedQualifierMatches = $competitionType === 'qualifier'
            ? (in_array((string)$competition['execution_model'],$this->context->workflowModels(),true) ? intdiv($selectedDeckCount,2) : $this->context->expectedQualifierMatches($selectedDeckCount, $participantsPerSide, $qualifierDuelsPerDeck))
            : 0;
        $scheduledQualifierMatches = 0;
        if ($competitionType === 'qualifier') {
            $scheduledQualifierMatches = $this->store->scheduledQualifierCount($competitionId);
        }
        $existingScheduledMatches = $this->store->pendingMatchCount($competitionId);
        $startDateReached = new DateTimeImmutable($startDate) <= $today;
        $competitionGate = $this->context->gate($competitionId);
        $canStartCompetition = $startDateReached && $canRunCompetition && $competitionGate['can_execute'] && ($competitionType !== 'qualifier' || ($expectedQualifierMatches > 0 && $scheduledQualifierMatches >= $expectedQualifierMatches));
        $transition = \StationX\Yugioh\Domain\Competitions\CompetitionConfigurationPolicy::transition(
            $postedStatus,
            $competitionType,
            $canRunCompetition,
            $canStartCompetition,
            $mode,
            $scheduledQualifierMatches,
        );
        $status = $transition['status'];
        $mode = $transition['mode'];
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

        return $this->transaction->run(function () use (
            $competitionId,
            $seasonId,
            $competition,
            $input,
            $mode,
            $selectedDeckIds,
            $deckRosterLocked,
            $canStartCompetition,
            $divisionId,
            $competitionName,
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
            $pattern,
            $intervalDays,
            $gameRuleId,
            $uploadedLogo,
            $canRunCompetition,
            $existingScheduledMatches,
            $expectedQualifierMatches,
            $auditBefore,
        ): int {
            $this->roster->validateLeagueRoster($competitionId, $selectedDeckIds);
            if (!$deckRosterLocked) {
                $this->store->replaceRoster($competitionId, $selectedDeckIds);
            }

            $shouldLock = $mode === 'start' && $canStartCompetition;
            $this->store->updateConfiguration([
                'division_id' => $divisionId,
                'name' => $competitionName !== '' ? $competitionName : (string) ($competition['name'] ?? 'Evento'),
                'type' => $competitionType,
                'event_ruleset' => $eventRuleset,
                'ruleset_version' => $rulesetVersion,
                'accent' => $accent,
                'logo_url' => $logoUrl,
                'rules' => $rules,
                'starts_on' => $startDate,
                'status' => $status,
                'match_format' => $matchFormat,
                'participants_per_side' => $participantsPerSide,
                'qualifier_duels_per_deck' => $qualifierDuelsPerDeck,
                'qualifier_matches_per_day' => $qualifierMatchesPerDay,
                'deck_pool' => $deckPoolMode,
                'promotion_slots' => $leaguePromotionSlots,
                'relegation_slots' => $leagueRelegationSlots,
                'promotion_to_division_id' => $leaguePromotionTo,
                'relegation_to_division_id' => $leagueRelegationTo,
                'lock_division_moves' => $lockDivisionMoves,
                'schedule_strategy' => $shouldLock ? 'auto_event' : (string) (($competition['schedule_strategy'] ?? '') ?: 'manual'),
                'schedule_pattern' => $pattern,
                'schedule_interval_days' => $intervalDays,
                'game_rule_id' => $gameRuleId ?: null,
                'lock_configuration' => $shouldLock ? 1 : 0,
                'mark_started' => $shouldLock ? 1 : 0,
                'competition_id' => $competitionId,
                'season_id' => $seasonId,
            ]);
            if (isset($input['division_movements']) && is_array($input['division_movements'])) {
                $this->roster->saveDivisionMovements($competitionId, $input['division_movements']);
            }
            if ($logoUrl !== '' && $logoUrl !== (string) ($competition['logo_url'] ?? '')) {
                $this->media->recordHistory('competition', $competitionId, $logoUrl, $uploadedLogo !== '' ? 'upload' : 'gallery');
            }

            $created = 0;
            if ($mode === 'schedule_qualifier' && $competitionType === 'qualifier' && $canRunCompetition) {
                $scheduleCompetition = array_merge($competition, ['id' => $competitionId, 'type' => $competitionType, 'division_id' => $divisionId ?: ($competition['division_id'] ?? 0), 'best_of' => $competition['best_of'] ?? 3]);
                $created = $this->schedule->scheduleQualifier($scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierDuelsPerDeck, $qualifierMatchesPerDay);
                $this->schedule->rebuildCalendar($seasonId, $competitionId, 'qualifier');
            } elseif ($mode === 'schedule_cup' && in_array($competitionType, ['cup', 'final'], true) && $canRunCompetition && $existingScheduledMatches === 0) {
                $scheduleCompetition = array_merge($competition, ['id' => $competitionId, 'type' => $competitionType, 'division_id' => $divisionId ?: ($competition['division_id'] ?? 0), 'best_of' => $competition['best_of'] ?? 3]);
                $created = $this->schedule->scheduleByModel($scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierMatchesPerDay);
                $this->schedule->rebuildCalendar($seasonId, $competitionId, $competitionType);
            } elseif ($mode === 'start' && $canRunCompetition && $existingScheduledMatches === 0) {
                $scheduleCompetition = array_merge($competition, [
                    'id' => $competitionId,
                    'type' => $competitionType,
                    'division_id' => $divisionId ?: ($competition['division_id'] ?? 0),
                    'best_of' => $competition['best_of'] ?? 3,
                    'execution_model' => $competition['execution_model'] ?? 'manual',
                    'phase_structure' => $competition['phase_structure'] ?? 'NONE',
                ]);
                $created = $this->schedule->scheduleByModel($scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierMatchesPerDay);
                if (in_array($competitionType, ['cup', 'final'], true) && $created < 1) {
                    throw new RuntimeException('No fue posible generar la llave de la copa antes de comenzar.');
                }
                if ($created > 0 && in_array($competitionType, ['cup', 'final'], true)) {
                    $this->schedule->rebuildCalendar($seasonId, $competitionId, $competitionType);
                }
            }
            if ($created > 0) {
                if($this->schedule->fixedProgram()) {
                    if($this->roster->teamKind($competition)==='' && !in_array((string)$competition['execution_model'],$this->context->workflowModels(),true) && $this->roster->admissionLoad($competition)===null)$this->schedule->reflow($competitionId,$startDate,$pattern,$qualifierMatchesPerDay);
                    $newDates = $this->store->scheduledRows($competitionId);
                    $this->schedule->validateSeasonSchedule($competitionId);
                    foreach($newDates() as $scheduled){
                        $valid=$this->schedule->validateMatchSchedule((int)$scheduled['id'],$scheduled['scheduled_on'],$scheduled['starts_at'],$scheduled['venue']);
                        if(empty($valid['ok'])) throw new InvalidArgumentException('La programacion nueva no cumple las restricciones de fecha, horario o participantes. El calendario existente se conserva.');
                    }
                } elseif($this->roster->teamKind($competition)==='' && !in_array((string)$competition['execution_model'],$this->context->workflowModels(),true) && $this->roster->admissionLoad($competition)===null)$this->schedule->rebalance($competitionId);
            }
            $this->schedule->persistCalendarEvent($seasonId, $competitionId);

            $conditionQualifierId = (int) ($input['condition_qualifier_id'] ?? 0);
            if($this->roster->admissionLoad($competition)!==null){ /* Typed admission owns its dependencies. */ }
            elseif (in_array($competitionType, ['league', 'tournament', 'world'], true)) {
                $this->store->clearDependencies($competitionId);
                if ($conditionQualifierId > 0) {
                    $validQualifierId = $this->store->validQualifier($seasonId, $conditionQualifierId);
                    if ($validQualifierId > 0) {
                        $this->store->setQualifierDependency($seasonId, $competitionId, $validQualifierId);
                    }
                }
            } else {
                $this->store->clearDependencies($competitionId);
            }
            $this->store->audit($competitionId, ['mode' => $mode, 'decks' => count($selectedDeckIds), 'deck_roster_locked' => $deckRosterLocked, 'matches' => $created, 'existing_scheduled_matches' => $existingScheduledMatches, 'reprogrammed' => $existingScheduledMatches > 0 && $created > 0, 'expected_matches' => $expectedQualifierMatches, 'start' => $startDate, 'pattern' => $pattern, 'match_format' => $matchFormat, 'participants_per_side' => $participantsPerSide, 'qualifier_duels_per_deck' => $qualifierDuelsPerDeck, 'qualifier_matches_per_day' => $qualifierMatchesPerDay, 'before'=>$auditBefore, 'after'=>$this->context->auditSnapshot($competitionId)]);
            $this->context->recordHistory($seasonId, $competitionId, (string) ($competition['status'] ?? ''), $status, 'configure_competition', ['mode' => $mode, 'matches' => $created]);
            $this->schedule->validateSeasonSchedule($competitionId);
            return $created;
        });
    }
}
