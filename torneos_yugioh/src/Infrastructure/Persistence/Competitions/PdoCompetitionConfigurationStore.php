<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionConfigurationStore;

final readonly class PdoCompetitionConfigurationStore implements CompetitionConfigurationStore
{
    public function __construct(private PDO $pdo) {}

    public function competition(int $seasonId, int $competitionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1',
        );
        $stmt->execute([$competitionId, $seasonId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function matchCount(int $competitionId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');
        $stmt->execute([$competitionId]);
        return (int) $stmt->fetchColumn();
    }

    public function hasRuleset(int $seasonId, string $eventRuleset): bool
    {
        $rulesetCheck = $this->pdo->prepare(
            'SELECT code FROM sx_ygo_rulesets WHERE season_id=? AND code=? AND active=1 LIMIT 1',
        );
        $rulesetCheck->execute([$seasonId, $eventRuleset]);
        return (bool) $rulesetCheck->fetchColumn();
    }

    public function resumeCancelled(int $seasonId, int $competitionId, string $nextStatus): void
    {
        $this->pdo
            ->prepare(
                '
            UPDATE sx_ygo_competitions
            SET status=?, configuration_locked=IF(? = "pending_configuration", 0, configuration_locked), started_at=IF(? = "pending_configuration", NULL, started_at)
            WHERE id=? AND season_id=?
        ',
            )
            ->execute([$nextStatus, $nextStatus, $nextStatus, $competitionId, $seasonId]);
    }

    public function audit(int $competitionId, array $payload): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                'competition',
                $competitionId,
                'configure_competition',
                json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
    }

    public function roster(int $competitionId): array
    {
        $currentDeckStmt = $this->pdo->prepare(
            'SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1 ORDER BY sort_order, id',
        );
        $currentDeckStmt->execute([$competitionId]);
        return array_values(array_map('intval', $currentDeckStmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function ruleCode(int $ruleId): string
    {
        $stmt = $this->pdo->prepare('SELECT code FROM sx_ygo_game_rules WHERE id = ? LIMIT 1');
        $stmt->execute([$ruleId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function scheduledQualifierCount(int $competitionId): int
    {
        $scheduledStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase IN ("qualifier","swiss") AND status <> "FINALIZADA" AND scheduled_on IS NOT NULL',
        );
        $scheduledStmt->execute([$competitionId]);
        return (int) $scheduledStmt->fetchColumn();
    }

    public function pendingMatchCount(int $competitionId): int
    {
        $existingMatchStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"',
        );
        $existingMatchStmt->execute([$competitionId]);
        return (int) $existingMatchStmt->fetchColumn();
    }

    public function replaceRoster(int $competitionId, array $selectedDeckIds): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_competition_decks SET active = 0 WHERE competition_id = ?')
            ->execute([$competitionId]);
        $deckInsert = $this->pdo->prepare('
            INSERT INTO sx_ygo_competition_decks (competition_id, deck_id, sort_order, active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), active=1
        ');
        foreach ($selectedDeckIds as $index => $deckId) {
            $deckInsert->execute([$competitionId, $deckId, $index + 1]);
        }
    }

    public function updateConfiguration(array $values): void
    {
        $this->pdo
            ->prepare(
                '
            UPDATE sx_ygo_competitions
            SET division_id=?, name=?, type=?, event_ruleset=?, ruleset_version=?, accent=?, logo_url=?, rules=?, starts_on=?, status=?, match_format=?, participants_per_side=?, qualifier_duels_per_deck=?, qualifier_matches_per_day=?, deck_pool=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, lock_division_moves=?, schedule_strategy=?, schedule_pattern=?, schedule_interval_days=?, game_rule_id=?, configuration_locked=IF(? = 1, 1, configuration_locked), started_at=IF(? = 1, COALESCE(started_at, NOW()), started_at)
            WHERE id=? AND season_id=?
        ',
            )
            ->execute([
                $values['division_id'],
                $values['name'],
                $values['type'],
                $values['event_ruleset'],
                $values['ruleset_version'],
                $values['accent'],
                $values['logo_url'],
                $values['rules'],
                $values['starts_on'],
                $values['status'],
                $values['match_format'],
                $values['participants_per_side'],
                $values['qualifier_duels_per_deck'],
                $values['qualifier_matches_per_day'],
                $values['deck_pool'],
                $values['promotion_slots'],
                $values['relegation_slots'],
                $values['promotion_to_division_id'],
                $values['relegation_to_division_id'],
                $values['lock_division_moves'],
                $values['schedule_strategy'],
                $values['schedule_pattern'],
                $values['schedule_interval_days'],
                $values['game_rule_id'],
                $values['lock_configuration'],
                $values['mark_started'],
                $values['competition_id'],
                $values['season_id'],
            ]);
    }

    public function scheduledRows(int $competitionId): \Closure
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_matches WHERE competition_id=?');
        $stmt->execute([$competitionId]);
        return static fn(): array => $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clearDependencies(int $competitionId): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')
            ->execute([$competitionId]);
    }

    public function validQualifier(int $seasonId, int $conditionQualifierId): int
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

    public function setQualifierDependency(
        int $seasonId,
        int $competitionId,
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
            ->execute([$seasonId, $competitionId, $validQualifierId]);
    }
}
