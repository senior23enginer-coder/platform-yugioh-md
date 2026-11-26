<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Matches;

use PDO;
use StationX\Yugioh\Application\Matches\MatchRuleReader;

final readonly class PdoMatchRuleReader implements MatchRuleReader
{
    public function __construct(private PDO $pdo) {}

    public function matchRules(int $matchId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.phase, m.game_rule_id AS match_rule_id, c.game_rule_id AS competition_rule_id
            FROM sx_ygo_matches m
            LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
            WHERE m.id = ?
            LIMIT 1
        ");
        $stmt->execute([$matchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeRule(int $ruleId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_game_rules WHERE id = ? AND active = 1 LIMIT 1',
        );
        $stmt->execute([$ruleId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function fallback(int $seasonId, array $scopes): int
    {
        $placeholders = implode(',', array_fill(0, count($scopes), '?'));
        $fallbackStmt = $this->pdo->prepare("
            SELECT id
            FROM sx_ygo_game_rules
            WHERE season_id = ?
              AND active = 1
              AND scope IN ($placeholders)
            ORDER BY FIELD(scope, $placeholders), priority_order, id
            LIMIT 1
        ");
        $fallbackStmt->execute([$seasonId, ...$scopes, ...$scopes]);
        return (int) ($fallbackStmt->fetchColumn() ?: 0);
    }

    public function defaultRule(int $seasonId): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM sx_ygo_game_rules
            WHERE season_id = ?
              AND active = 1
              AND scope IN ('event', 'all_matches', 'general')
            ORDER BY FIELD(scope, 'event', 'all_matches', 'general'), priority_order, id
            LIMIT 1
        ");
        $stmt->execute([$seasonId]);
        $ruleId = (int) ($stmt->fetchColumn() ?: 0);
        return $ruleId > 0 ? $ruleId : null;
    }

    public function postedRule(int $seasonId, int $ruleId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_game_rules WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1',
        );
        $stmt->execute([$ruleId, $seasonId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function postedMatchRule(int $seasonId, int $ruleId, array $scopes): ?int
    {
        $placeholders = implode(',', array_fill(0, count($scopes), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM sx_ygo_game_rules
            WHERE id = ?
              AND season_id = ?
              AND active = 1
              AND scope IN ($placeholders)
            LIMIT 1
        ");
        $stmt->execute([$ruleId, $seasonId, ...$scopes]);
        $ruleId = (int) ($stmt->fetchColumn() ?: 0);
        return $ruleId > 0 ? $ruleId : null;
    }

    public function bestOf(int $matchId): mixed
    {
        $stmt = $this->pdo->prepare('SELECT best_of FROM sx_ygo_matches WHERE id=? LIMIT 1');
        $stmt->execute([$matchId]);
        return $stmt->fetchColumn();
    }

    public function conditions(int $ruleId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sx_ygo_game_rule_conditions WHERE game_rule_id=? AND active=1 AND operator='sequence_equals' ORDER BY sort_order,id",
        );
        $stmt->execute([$ruleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
