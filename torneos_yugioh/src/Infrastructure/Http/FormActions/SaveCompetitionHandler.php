<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Native save_competition adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveCompetitionHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $id = (int) ($post['competition_id'] ?? 0);
        $competitionName = trim((string) $post['competition_name']);
        $competitionType = trim((string) $post['competition_type']);
        $postedDivision = trim((string) ($post['competition_division_id'] ?? ''));
        $postedDivisionIds = $post['competition_division_ids'] ?? [];
        if (!is_array($postedDivisionIds)) {
            $postedDivisionIds = [];
        }
        $postedDivisionIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $postedDivisionIds),
                    static fn(int $divisionId): bool => $divisionId > 0,
                ),
            ),
        );
        $competitionRuleset = trim((string) ($post['competition_event_ruleset'] ?? 'tcg_advanced'));
        if (str_starts_with($postedDivision, 'format:')) {
            $competitionRuleset = substr($postedDivision, 7) ?: $competitionRuleset;
        }
        $rulesetStmt = $pdo->prepare(
            'SELECT * FROM sx_ygo_rulesets WHERE season_id = ? AND code = ? AND active = 1 LIMIT 1',
        );
        $rulesetStmt->execute([$seasonId, $competitionRuleset]);
        $selectedRuleset = $rulesetStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $existingCompetition = [];
        if ($id > 0) {
            $existingStmt = $pdo->prepare("
                SELECT c.*, r.code AS game_rule_code
                FROM sx_ygo_competitions c
                LEFT JOIN sx_ygo_game_rules r ON r.id = c.game_rule_id
                WHERE c.id = ? AND c.season_id = ?
                LIMIT 1
            ");
            $existingStmt->execute([$id, $seasonId]);
            $existingCompetition = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
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
            ygo_assert_fixture_mutable($pdo, $id);
        }
        $competitionCode = $id > 0 ? trim((string) ($existingCompetition['code'] ?? '')) : '';
        if ($competitionCode === '' && $competitionName !== '') {
            $competitionCode = ygo_generate_competition_code(
                $pdo,
                $seasonId,
                $competitionType,
                $competitionRuleset,
                $competitionName,
                $id,
            );
        }
        $matchFormat = trim(
            (string) ($post['competition_match_format'] ??
                ($selectedRuleset['match_format'] ?? '1v1')),
        );
        if (!in_array($matchFormat, ['1v1', '2v2', '3v3', 'ffa'], true)) {
            $matchFormat = '1v1';
        }
        $participantsPerSide = $matchFormat === 'ffa' ? 1 : (int) substr($matchFormat, 0, 1);
        $postedDivisionId =
            $postedDivision !== '' && ctype_digit($postedDivision) && (int) $postedDivision > 0
                ? (int) $postedDivision
                : null;
        if ($postedDivisionId && !in_array($postedDivisionId, $postedDivisionIds, true)) {
            array_unshift($postedDivisionIds, $postedDivisionId);
        }
        if ($competitionType === 'league' && !$postedDivisionIds && $id > 0) {
            $linkedDivisionStmt = $pdo->prepare(
                'SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND league_competition_id=? ORDER BY tier_order,id',
            );
            $linkedDivisionStmt->execute([$seasonId, $id]);
            $postedDivisionIds = array_map(
                'intval',
                $linkedDivisionStmt->fetchAll(PDO::FETCH_COLUMN),
            );
        }
        if (
            $competitionType === 'league' &&
            !$postedDivisionIds &&
            !empty($existingCompetition['division_id'])
        ) {
            $postedDivisionIds = [(int) $existingCompetition['division_id']];
        }
        if ($competitionType === 'league' && $postedDivisionIds) {
            $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
            $divisionStmt = $pdo->prepare(
                "SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND id IN ($placeholders) ORDER BY FIELD(id,$placeholders)",
            );
            $divisionStmt->execute(
                array_merge([$seasonId], $postedDivisionIds, $postedDivisionIds),
            );
            $postedDivisionIds = array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN));
            $postedDivisionId =
                $postedDivisionId && in_array($postedDivisionId, $postedDivisionIds, true)
                    ? $postedDivisionId
                    : $postedDivisionIds[0] ?? null;
        }
        if ($competitionType === 'league' && !$postedDivisionId) {
            throw new RuntimeException('Toda liga debe tener una division activa asociada.');
        }
        $deckPoolMode = (string) ($post['competition_deck_pool'] ?? 'all_active');
        if (!in_array($deckPoolMode, ['all_active', 'division'], true)) {
            $deckPoolMode = 'all_active';
        }
        if ($deckPoolMode === 'division' && (!$postedDivisionId || $postedDivisionId <= 0)) {
            $deckPoolMode = 'all_active';
        }
        $hasLeagueMovements = $competitionType === 'league' && count($postedDivisionIds) > 1;
        $leaguePromotionSlots = $hasLeagueMovements
            ? max(0, (int) ($post['competition_promotion_slots'] ?? 0))
            : 0;
        $leagueRelegationSlots = $hasLeagueMovements
            ? max(0, (int) ($post['competition_relegation_slots'] ?? 0))
            : 0;
        $leaguePromotionTo =
            $hasLeagueMovements && ($post['competition_promotion_to'] ?? '') !== ''
                ? (int) $post['competition_promotion_to']
                : null;
        $leagueRelegationTo =
            $hasLeagueMovements && ($post['competition_relegation_to'] ?? '') !== ''
                ? (int) $post['competition_relegation_to']
                : null;
        if ($postedDivisionId && $leaguePromotionTo === $postedDivisionId) {
            $leaguePromotionTo = null;
        }
        if ($postedDivisionId && $leagueRelegationTo === $postedDivisionId) {
            $leagueRelegationTo = null;
        }
        $lockDivisionMoves =
            $hasLeagueMovements && isset($post['competition_lock_division_moves']) ? 1 : 0;
        $qualifierDuelsPerDeck =
            $competitionType === 'qualifier'
                ? max(2, min(5, (int) ($post['qualifier_duels_per_deck'] ?? 3)))
                : 3;
        $qualifierMatchesPerDay =
            $competitionType === 'qualifier'
                ? max(1, min(5, (int) ($post['qualifier_matches_per_day'] ?? 1)))
                : 1;
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
        $templateType = $promptTemplate !== '' ? ygo_event_template_type($promptTemplate) : '';
        if ($templateType !== '' && $templateType !== $competitionType) {
            throw new InvalidArgumentException(
                'El tipo de evento no corresponde a la plantilla elegida; no se borrara ni sustituira silenciosamente.',
            );
        }
        ygo_require_execution_type($competitionType, $executionModel);
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
        $choiceErrors = ygo_event_choice_errors([
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
            !in_array($promptTemplate, array_column(ygo_event_available_templates(), 'code'), true)
        ) {
            throw new InvalidArgumentException('Modelo preconfigurado desconocido.');
        }
        if ($competitionType === 'friendly') {
            $rankingImpact = 'NONE';
            $level = $level ?: 'CASUAL';
        }
        $uploadedLogo = ygo_store_event_logo_upload();
        $galleryLogo = ygo_valid_event_gallery_logo(
            (string) ($post['competition_gallery_logo'] ?? ''),
            $competitionType,
        );
        $eventTypeAccent =
            (string) ($GLOBALS['competitionTypeOptions'][$competitionType]['accent'] ?? '' ?:
            '#36d8ff');
        $defaultLogos = ygo_event_logo_gallery()[$competitionType] ?? [];
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
        $gameRuleId = ygo_valid_game_rule_id(
            $pdo,
            $seasonId,
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
        $operationalParameters = [];
        foreach (
            ['swiss_rounds', 'top_n', 'group_max_size', 'group_qualifiers', 'series_matches']
            as $parameter
        ) {
            $posted = trim((string) ($post['competition_parameter_' . $parameter] ?? ''));
            if ($posted === '') {
                continue;
            }
            $value = filter_var($posted, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 64) {
                throw new InvalidArgumentException('Parametro operativo invalido: ' . $parameter);
            }
            $operationalParameters[$parameter] = $value;
        }
        if (isset($operationalParameters['series_matches'])) {
            if ($operationalParameters['series_matches'] % 2 !== 1) {
                throw new InvalidArgumentException(
                    'La serie debe tener un numero impar de matches.',
                );
            }
            $operationalParameters['series_wins'] =
                intdiv($operationalParameters['series_matches'], 2) + 1;
        }
        foreach (array_keys($operationalParameters) as $key) {
            if (!in_array($key, ygo_execution_parameter_fields($executionModel), true)) {
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
        foreach (ygo_event_available_templates() as $t) {
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
        $contractErrors = ygo_event_contract_errors(
            [
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
        if ($values[2] !== '' && $values[3] !== '') {
            $targetCompetitionId = $id;
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE sx_ygo_competitions SET division_id=?, code=?, name=?, type=?, scope=?, format=?, match_format=?, best_of=?, participants_per_side=?, schedule_strategy=?, event_ruleset=?, ruleset_version=?, deck_pool=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, lock_division_moves=?, victory_condition=?, defeat_condition=?, draw_condition=?, prompt_template=?, event_system=?, execution_model=?, participation=?, construction=?, environment=?, level=?, event_flow=?, participant_source=?, advancement_rule=?, draw_policy=?, ranking_impact=?, deck_policy=?, access_type=?, phase_structure=?, qualifier_duels_per_deck=?, qualifier_matches_per_day=?, game_rule_id=?, status=?, starts_on=?, ends_on=?, accent=?, logo_url=?, rules=?, active=1 WHERE id=?',
                )->execute(array_merge(array_slice($values, 1), [$id]));
                ygo_record_competition_status_history(
                    $pdo,
                    $seasonId,
                    $id,
                    (string) ($existingCompetition['status'] ?? ''),
                    $competitionStatus,
                    'save_competition',
                    ['source' => 'create_event_screen'],
                );
            } else {
                $pdo->prepare(
                    'INSERT INTO sx_ygo_competitions (season_id, division_id, code, name, type, scope, format, match_format, best_of, participants_per_side, schedule_strategy, event_ruleset, ruleset_version, deck_pool, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, lock_division_moves, victory_condition, defeat_condition, draw_condition, prompt_template, event_system, execution_model, participation, construction, environment, level, event_flow, participant_source, advancement_rule, draw_policy, ranking_impact, deck_policy, access_type, phase_structure, qualifier_duels_per_deck, qualifier_matches_per_day, game_rule_id, status, starts_on, ends_on, accent, logo_url, rules, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                )->execute($values);
                $targetCompetitionId = (int) $pdo->lastInsertId();
                ygo_record_competition_status_history(
                    $pdo,
                    $seasonId,
                    $targetCompetitionId,
                    null,
                    $competitionStatus,
                    'save_competition',
                    ['source' => 'create_event_screen'],
                );
            }
            if ($targetCompetitionId > 0) {
                if ($competitionType === 'league' && $postedDivisionIds) {
                    $pdo->prepare(
                        'UPDATE sx_ygo_divisions SET league_competition_id=NULL WHERE season_id=? AND league_competition_id=?',
                    )->execute([$seasonId, $targetCompetitionId]);
                    $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
                    $pdo->prepare(
                        "UPDATE sx_ygo_divisions SET league_competition_id=? WHERE season_id=? AND active=1 AND id IN ($placeholders)",
                    )->execute(array_merge([$targetCompetitionId, $seasonId], $postedDivisionIds));
                }
                $sameRecipe =
                    ($existingCompetition['prompt_template'] ?? null) === $promptTemplate &&
                    ($existingCompetition['execution_model'] ?? null) === $executionModel;
                $pdo->prepare(
                    'UPDATE sx_ygo_competitions SET execution_parameters=? WHERE id=?',
                )->execute([
                    $operationalParameters
                        ? json_encode($operationalParameters)
                        : ($sameRecipe
                            ? $existingCompetition['execution_parameters'] ?? null
                            : null),
                    $targetCompetitionId,
                ]);
                ygo_record_logo_history(
                    $pdo,
                    'competition',
                    $targetCompetitionId,
                    $logoUrl,
                    $uploadedLogo !== '' ? 'upload' : ($galleryLogo !== '' ? 'gallery' : 'default'),
                );
                ygo_persist_competition_calendar_event($pdo, $seasonId, $targetCompetitionId);
            }
            $conditionQualifierId = (int) ($post['condition_qualifier_id'] ?? 0);
            if (
                $targetCompetitionId > 0 &&
                in_array($competitionType, ['league', 'tournament', 'world'], true)
            ) {
                $pdo->prepare(
                    'UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?',
                )->execute([$targetCompetitionId]);
                if ($conditionQualifierId > 0) {
                    $qualifierStmt = $pdo->prepare("
                        SELECT c.id
                        FROM sx_ygo_competitions c
                        WHERE c.id=? AND c.season_id=? AND c.type='qualifier' AND c.active=1
                          AND (
                            LOWER(REPLACE(c.status, ' ', '_')) IN ('pending_event', 'pendiente_evento')
                            OR (
                              LOWER(REPLACE(c.status, ' ', '_')) = 'pending_configuration'
                              AND (SELECT COUNT(*) FROM sx_ygo_competition_decks cd WHERE cd.competition_id = c.id AND cd.active = 1) >= 4
                            )
                          )
                        LIMIT 1
                    ");
                    $qualifierStmt->execute([$conditionQualifierId, $seasonId]);
                    $validQualifierId = (int) ($qualifierStmt->fetchColumn() ?: 0);
                    if ($validQualifierId > 0) {
                        $pdo->prepare(
                            '
                            INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
                            VALUES (?, ?, ?, "completed_with_results", 0, 1)
                            ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
                        ',
                        )->execute([$seasonId, $targetCompetitionId, $validQualifierId]);
                    }
                }
            }
        }
        return $targetCompetitionId > 0 ? 'evento_configurado_' . $targetCompetitionId : 'evento';

        return null;
    }
}
