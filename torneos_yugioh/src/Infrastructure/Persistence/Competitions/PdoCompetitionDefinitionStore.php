<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionDefinitionStore;

final readonly class PdoCompetitionDefinitionStore implements CompetitionDefinitionStore
{
    public function __construct(private PDO $pdo) {}

    public function ruleset(int $seasonId, string $competitionRuleset): array
    {
        $rulesetStmt = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_rulesets WHERE season_id = ? AND code = ? AND active = 1 LIMIT 1',
        );
        $rulesetStmt->execute([$seasonId, $competitionRuleset]);
        return $rulesetStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function existing(int $id, int $seasonId): array
    {
        $existingStmt = $this->pdo->prepare("
            SELECT c.*, r.code AS game_rule_code
            FROM sx_ygo_competitions c
            LEFT JOIN sx_ygo_game_rules r ON r.id = c.game_rule_id
            WHERE c.id = ? AND c.season_id = ?
            LIMIT 1
        ");
        $existingStmt->execute([$id, $seasonId]);
        return $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function linkedDivisions(int $seasonId, int $id): array
    {
        $linkedDivisionStmt = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND league_competition_id=? ORDER BY tier_order,id',
        );
        $linkedDivisionStmt->execute([$seasonId, $id]);
        return array_map('intval', $linkedDivisionStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function activeDivisions(int $seasonId, array $postedDivisionIds): array
    {
        $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
        $divisionStmt = $this->pdo->prepare(
            "SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND id IN ($placeholders) ORDER BY FIELD(id,$placeholders)",
        );
        $divisionStmt->execute(array_merge([$seasonId], $postedDivisionIds, $postedDivisionIds));
        return array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function update(int $id, array $values): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_competitions SET division_id=?, code=?, name=?, type=?, scope=?, format=?, match_format=?, best_of=?, participants_per_side=?, schedule_strategy=?, event_ruleset=?, ruleset_version=?, deck_pool=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, lock_division_moves=?, victory_condition=?, defeat_condition=?, draw_condition=?, prompt_template=?, event_system=?, execution_model=?, participation=?, construction=?, environment=?, level=?, event_flow=?, participant_source=?, advancement_rule=?, draw_policy=?, ranking_impact=?, deck_policy=?, access_type=?, phase_structure=?, qualifier_duels_per_deck=?, qualifier_matches_per_day=?, game_rule_id=?, status=?, starts_on=?, ends_on=?, accent=?, logo_url=?, rules=?, active=1 WHERE id=?',
            )
            ->execute(array_merge(array_slice($values, 1), [$id]));
    }

    public function insert(array $values): int
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_competitions (season_id, division_id, code, name, type, scope, format, match_format, best_of, participants_per_side, schedule_strategy, event_ruleset, ruleset_version, deck_pool, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, lock_division_moves, victory_condition, defeat_condition, draw_condition, prompt_template, event_system, execution_model, participation, construction, environment, level, event_flow, participant_source, advancement_rule, draw_policy, ranking_impact, deck_policy, access_type, phase_structure, qualifier_duels_per_deck, qualifier_matches_per_day, game_rule_id, status, starts_on, ends_on, accent, logo_url, rules, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            )
            ->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function linkDivisions(
        int $seasonId,
        int $targetCompetitionId,
        array $postedDivisionIds,
    ): void {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_divisions SET league_competition_id=NULL WHERE season_id=? AND league_competition_id=?',
            )
            ->execute([$seasonId, $targetCompetitionId]);
        $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
        $this->pdo
            ->prepare(
                "UPDATE sx_ygo_divisions SET league_competition_id=? WHERE season_id=? AND active=1 AND id IN ($placeholders)",
            )
            ->execute(array_merge([$targetCompetitionId, $seasonId], $postedDivisionIds));
    }

    public function setParameters(int $targetCompetitionId, ?string $parameters): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_competitions SET execution_parameters=? WHERE id=?')
            ->execute([$parameters, $targetCompetitionId]);
    }

    public function clearDependencies(int $targetCompetitionId): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')
            ->execute([$targetCompetitionId]);
    }

    public function validQualifier(int $conditionQualifierId, int $seasonId): int
    {
        $qualifierStmt = $this->pdo->prepare("
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
        return (int) ($qualifierStmt->fetchColumn() ?: 0);
    }

    public function setDependency(
        int $seasonId,
        int $targetCompetitionId,
        int $validQualifierId,
    ): void {
        $this->pdo
            ->prepare(
                '
            INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
            VALUES (?, ?, ?, "completed_with_results", 0, 1)
            ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
        ',
            )
            ->execute([$seasonId, $targetCompetitionId, $validQualifierId]);
    }
}
