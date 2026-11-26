<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;
use RuntimeException;
use StationX\Yugioh\Domain\Competitions\CompetitionCreationPolicy;

final readonly class SaveCompetitionDefinition
{
    public function __construct(private CompetitionDefinitionStore $store, private CompetitionDefinitionServices $services) {}

    public function execute(array $post, array $typeOptions): string
    {
        $seasonId = $this->services->currentSeasonId();
        $id = (int) ($post['competition_id'] ?? 0);
        $competitionName = trim((string) $post['competition_name']);
        $competitionType = trim((string) $post['competition_type']);
        $postedDivision = trim((string) ($post['competition_division_id'] ?? ''));
        $selection = CompetitionCreationPolicy::selection($post, $postedDivision);
        $postedDivisionIds = $selection['divisionIds'];
        $competitionRuleset = $selection['ruleset'];
        $selectedRuleset = $this->store->ruleset($seasonId, $competitionRuleset);
        $existingCompetition = [];
        if ($id > 0) {
            $existingCompetition = $this->store->existing($id, $seasonId);
            if (!empty($existingCompetition['type'])) {
                $competitionType = (string) $existingCompetition['type'];
            }
        }
        if ($id > 0 && !empty($existingCompetition['configuration_locked'])) {
            throw new InvalidArgumentException(
                'El evento esta bloqueado. Usa su ficha sin reescribir la definicion ni el calendario.',
            );
        }
        if ($id > 0) {
            $this->services->assertMutable($id);
        }
        $competitionCode = $id > 0 ? trim((string) ($existingCompetition['code'] ?? '')) : '';
        if ($competitionCode === '' && $competitionName !== '') {
            $competitionCode = $this->services->generateCode($seasonId,
                $competitionType,
                $competitionRuleset,
                $competitionName,
                $id,
            );
        }
        $formatInput = CompetitionCreationPolicy::format($post, $selectedRuleset, $postedDivision, $postedDivisionIds);
        $matchFormat = $formatInput['matchFormat'];
        $participantsPerSide = $formatInput['participantsPerSide'];
        $postedDivisionId = $formatInput['divisionId'];
        $postedDivisionIds = $formatInput['divisionIds'];
        if ($competitionType === 'league' && !$postedDivisionIds && $id > 0) {
            $postedDivisionIds = $this->store->linkedDivisions($seasonId, $id);
        }
        if (
            $competitionType === 'league' &&
            !$postedDivisionIds &&
            !empty($existingCompetition['division_id'])
        ) {
            $postedDivisionIds = [(int) $existingCompetition['division_id']];
        }
        if ($competitionType === 'league' && $postedDivisionIds) {
            $postedDivisionIds = $this->store->activeDivisions($seasonId, $postedDivisionIds);
            $postedDivisionId =
                $postedDivisionId && in_array($postedDivisionId, $postedDivisionIds, true)
                    ? $postedDivisionId
                    : $postedDivisionIds[0] ?? null;
        }
        if ($competitionType === 'league' && !$postedDivisionId) {
            throw new RuntimeException('Toda liga debe tener una division activa asociada.');
        }
        [
            'deckPoolMode' => $deckPoolMode, 'leaguePromotionSlots' => $leaguePromotionSlots,
            'leagueRelegationSlots' => $leagueRelegationSlots, 'leaguePromotionTo' => $leaguePromotionTo,
            'leagueRelegationTo' => $leagueRelegationTo, 'lockDivisionMoves' => $lockDivisionMoves,
            'qualifierDuelsPerDeck' => $qualifierDuelsPerDeck, 'qualifierMatchesPerDay' => $qualifierMatchesPerDay,
        ] = CompetitionCreationPolicy::movements($post, $competitionType, $postedDivisionId, $postedDivisionIds);
        $rulesetVersion = trim((string) ($post['competition_ruleset_version'] ?? ''));
        if (
            $id === 0 &&
            $competitionRuleset !== 'goat' &&
            preg_match('/(?:abril|april)\s*2005|goat/i', $rulesetVersion)
        ) {
            throw new InvalidArgumentException(
                'La version GOAT/Abril 2005 no corresponde al formato elegido. Selecciona su reglamento.',
            );
        }
        $promptTemplate = preg_replace(
            '/[^a-zA-Z0-9_.-]/',
            '',
            trim((string) ($post['competition_prompt_template'] ?? '')),
        );
        $eventSystem = trim((string) ($post['competition_event_system'] ?? ''));
        $executionModel = preg_replace(
            '/[^a-zA-Z0-9_.-]/',
            '',
            trim((string) ($post['competition_execution_model'] ?? '')),
        );
        $templateType = $promptTemplate !== '' ? $this->services->templateType($promptTemplate) : '';
        if ($templateType !== '' && $templateType !== $competitionType) {
            throw new InvalidArgumentException(
                'El tipo de evento no corresponde a la plantilla elegida; no se borrara ni sustituira silenciosamente.',
            );
        }
        $this->services->requireExecutionType($competitionType, $executionModel);
        $participation =
            $matchFormat === 'ffa'
                ? 'FREE_FOR_ALL'
                : ($matchFormat === '3v3'
                    ? 'TEAM_3V3'
                    : ($matchFormat === '2v2'
                        ? 'TAG_2V2'
                        : 'INDIVIDUAL_1V1'));
        $construction = trim((string) ($post['competition_construction'] ?? ''));
        $environment = trim((string) ($post['competition_environment'] ?? ''));
        $level = trim((string) ($post['competition_level'] ?? ''));
        $eventFlow = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(trim((string) ($post['competition_event_flow'] ?? 'STANDALONE'))),
        );
        $participantSource = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(trim((string) ($post['competition_participant_source'] ?? 'MANUAL'))),
        );
        $accessType = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(trim((string) ($post['competition_access_type'] ?? 'OPEN'))),
        );
        $advancementRule = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(
                trim((string) ($post['competition_advancement_rule'] ?? 'MANUAL_SELECTION')),
            ),
        );
        $drawPolicy = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(trim((string) ($post['competition_draw_policy'] ?? 'USE_TIEBREAKER'))),
        );
        $rankingImpact = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(trim((string) ($post['competition_ranking_impact'] ?? 'FULL'))),
        );
        $deckPolicy = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(
                trim((string) ($post['competition_deck_policy'] ?? 'OPTIONAL_REGISTRATION')),
            ),
        );
        $phaseStructure = preg_replace(
            '/[^A-Z0-9_]/',
            '',
            strtoupper(trim((string) ($post['competition_phase_structure'] ?? 'NONE'))),
        );
        $choiceErrors = $this->services->choiceErrors([
            'participation' => $participation,
            'construction' => $construction,
            'environment' => $environment,
            'level' => $level,
            'event_flow' => $eventFlow,
            'participant_source' => $participantSource,
            'access_type' => $accessType,
            'advancement_rule' => $advancementRule,
            'draw_policy' => $drawPolicy,
            'ranking_impact' => $rankingImpact,
            'deck_policy' => $deckPolicy,
            'phase_structure' => $phaseStructure,
        ]);
        if ($choiceErrors) {
            throw new InvalidArgumentException(implode(' ', $choiceErrors));
        }
        if (
            $promptTemplate !== '' &&
            !in_array($promptTemplate, array_column($this->services->templates(), 'code'), true)
        ) {
            throw new InvalidArgumentException('Modelo preconfigurado desconocido.');
        }
        if ($competitionType === 'friendly') {
            $rankingImpact = 'NONE';
            $level = $level ?: 'CASUAL';
        }
        $uploadedLogo = $this->services->uploadLogo();
        $galleryLogo = $this->services->validGalleryLogo((string) ($post['competition_gallery_logo'] ?? ''),
            $competitionType,
        );
        $eventTypeAccent =
            (string) ($typeOptions[$competitionType]['accent'] ?? '' ?:
            '#36d8ff');
        $defaultLogos = $this->services->gallery()[$competitionType] ?? [];
        $defaultLogo = (string) ($defaultLogos[0]['url'] ?? '');
        $logoUrl =
            $uploadedLogo !== ''
                ? $uploadedLogo
                : ($galleryLogo !== ''
                    ? $galleryLogo
                    : ((string) ($existingCompetition['logo_url'] ?? '') ?:
                    $defaultLogo));
        $postedCreateStatus = trim(
            (string) ($post['competition_status'] ?? 'pending_configuration'),
        );
        $competitionStatus =
            $postedCreateStatus === 'cancelled' && $id > 0 ? 'cancelled' : 'pending_configuration';
        $existingRuleCode = (string) ($existingCompetition['game_rule_code'] ?? '');
        $fallbackRuleId = (int) ($existingCompetition['game_rule_id'] ?? 0) ?: null;
        $gameRuleId = $this->services->validGameRule($seasonId,
            (int) ($post['competition_game_rule_id'] ?? 0),
            $fallbackRuleId,
            $competitionType,
            $executionModel,
        );
        $bestOf = filter_var(
            $post['competition_best_of'] ?? ($selectedRuleset['best_of'] ?? 3),
            FILTER_VALIDATE_INT,
        );
        if ($bestOf === false || $bestOf < 1 || $bestOf > 5) {
            throw new InvalidArgumentException(
                'Mejor de debe ser un entero entre 1 y 5; no se permite D6.',
            );
        }
        if ($bestOf === 1 && empty($post['competition_game_rule_id'])) {
            $gameRuleId = null;
        }
        if (
            in_array($promptTemplate, ['team_league', 'team_cup', 'team_championship'], true) &&
            !in_array($matchFormat, ['2v2', '3v3'], true)
        ) {
            throw new InvalidArgumentException(
                'Selecciona expresamente 2v2 o 3v3 para este modelo de equipos.',
            );
        }
        $operationalParameters = CompetitionCreationPolicy::parameters($post);
        foreach (array_keys($operationalParameters) as $key) {
            if (!in_array($key, $this->services->parameterFields($executionModel), true)) {
                throw new InvalidArgumentException(
                    'El parametro no se aplica al sistema elegido: ' . $key,
                );
            }
        }
        if (
            $promptTemplate === 'clasificatorio_divisiones_3_duelos' &&
            (($operationalParameters['swiss_rounds'] ?? 3) > 3 || $qualifierDuelsPerDeck > 3)
        ) {
            throw new InvalidArgumentException(
                'Esta plantilla admite hasta tres rondas/enfrentamientos por deck; elige Suizo personalizado para otro limite.',
            );
        }
        if (
            isset($operationalParameters['group_max_size']) &&
            $operationalParameters['group_max_size'] < 2
        ) {
            throw new InvalidArgumentException(
                'Un grupo competitivo requiere al menos dos participantes.',
            );
        }
        if (
            $executionModel === 'groups_knockout' &&
            ($operationalParameters['group_qualifiers'] ?? 2) >
                ($operationalParameters['group_max_size'] ?? 4)
        ) {
            throw new InvalidArgumentException('Los cupos por grupo superan su capacidad.');
        }
        $preset = null;
        foreach ($this->services->templates() as $t) {
            if ($t['code'] === $promptTemplate) {
                $preset = $t;
            }
        }
        if ($executionModel === 'free_for_all') {
            $rankingImpact = 'NONE';
            $gameRuleId = null;
            if ($matchFormat !== 'ffa' || $bestOf !== 1) {
                throw new InvalidArgumentException(
                    'Battle Royale requiere participacion multijugador y un duelo.',
                );
            }
        }
        $contractErrors = $this->services->contractErrors([
                'type' => $competitionType,
                'prompt_template' => $promptTemplate,
                'execution_model' => $executionModel,
                'phase_structure' => $phaseStructure,
                'advancement_rule' => $advancementRule,
                'best_of' => $bestOf,
                'participant_source' => $participantSource,
                'match_format' => $matchFormat,
                'event_ruleset' => $competitionRuleset,
            ],
            $preset,
        );
        if ($contractErrors) {
            throw new InvalidArgumentException(implode(' ', $contractErrors));
        }
        $values = [
            $seasonId,
            $postedDivisionId,
            $competitionCode,
            $competitionName,
            $competitionType,
            trim((string) ($post['competition_scope'] ?? 'division')),
            trim((string) ($post['competition_format'] ?? 'editable')),
            $matchFormat,
            $bestOf,
            max(1, min(3, $participantsPerSide)),
            trim(
                (string) ($selectedRuleset['schedule_strategy'] ??
                    ($post['competition_schedule_strategy'] ?? 'manual')),
            ),
            $competitionRuleset,
            $rulesetVersion,
            $deckPoolMode,
            $leaguePromotionSlots,
            $leagueRelegationSlots,
            $leaguePromotionTo,
            $leagueRelegationTo,
            $lockDivisionMoves,
            trim(
                (string) ($selectedRuleset['victory_condition'] ??
                    'Resultado definido por reglamento'),
            ),
            trim(
                (string) ($selectedRuleset['defeat_condition'] ??
                    'Resultado definido por reglamento'),
            ),
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
            $post['starts_on'] ?? '' ?: null,
            $post['ends_on'] ?? '' ?: null,
            $eventTypeAccent,
            $logoUrl,
            trim((string) $post['competition_rules']),
        ];
        $targetCompetitionId = 0;
        if ($values[2] !== '' && $values[3] !== '') {
            $targetCompetitionId = $id;
            if ($id > 0) {
                $this->store->update($id, $values);
                $this->services->history($seasonId,
                    $id,
                    (string) ($existingCompetition['status'] ?? ''),
                    $competitionStatus,
                    'save_competition',
                    ['source' => 'create_event_screen'],
                );
            } else {
                $targetCompetitionId = $this->store->insert($values);
                $this->services->history($seasonId,
                    $targetCompetitionId,
                    null,
                    $competitionStatus,
                    'save_competition',
                    ['source' => 'create_event_screen'],
                );
            }
            if ($targetCompetitionId > 0) {
                if ($competitionType === 'league' && $postedDivisionIds) {
                    $this->store->linkDivisions($seasonId, $targetCompetitionId, $postedDivisionIds);
                }
                $sameRecipe =
                    ($existingCompetition['prompt_template'] ?? null) === $promptTemplate &&
                    ($existingCompetition['execution_model'] ?? null) === $executionModel;
                $this->store->setParameters($targetCompetitionId,                     $operationalParameters
                        ? json_encode($operationalParameters)
                        : ($sameRecipe
                            ? $existingCompetition['execution_parameters'] ?? null
                            : null));
                $this->services->recordLogo('competition',
                    $targetCompetitionId,
                    $logoUrl,
                    $uploadedLogo !== '' ? 'upload' : ($galleryLogo !== '' ? 'gallery' : 'default'),
                );
                $this->services->persistCalendar($seasonId, $targetCompetitionId);
            }
            $conditionQualifierId = (int) ($post['condition_qualifier_id'] ?? 0);
            if (
                $targetCompetitionId > 0 &&
                in_array($competitionType, ['league', 'tournament', 'world'], true)
            ) {
                $this->store->clearDependencies($targetCompetitionId);
                if ($conditionQualifierId > 0) {
                    $validQualifierId = $this->store->validQualifier($conditionQualifierId, $seasonId);
                    if ($validQualifierId > 0) {
                        $this->store->setDependency($seasonId, $targetCompetitionId, $validQualifierId);
                    }
                }
            }
        }
        return $targetCompetitionId > 0 ? 'evento_configurado_' . $targetCompetitionId : 'evento';
    }
}
