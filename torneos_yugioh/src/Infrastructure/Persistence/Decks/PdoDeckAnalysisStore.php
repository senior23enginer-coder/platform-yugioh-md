<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckAnalysisStore;

final readonly class PdoDeckAnalysisStore implements DeckAnalysisStore
{
    public function __construct(private PDO $pdo) {}

    public function activeDeck(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function activePair(int $deckId, int $opponentId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sx_ygo_decks WHERE id IN (?, ?) AND active=1',
        );
        $stmt->execute([$deckId, $opponentId]);
        return (int) $stmt->fetchColumn() === 2;
    }

    public function saveProfile(int $deckId, array $values): void
    {
        $this->pdo
            ->prepare(
                "
            INSERT INTO sx_ygo_deck_analysis_profiles
                (deck_id, play_style, pace, complexity, primary_game_plan, win_condition, resource_engine, opening_plan, mid_game_plan, late_game_plan, going_first_plan, going_second_plan, side_deck_strategy, strengths_json, weaknesses_json, strategy_tags_json, consistency_score, ceiling_score, resilience_score, interaction_score, recovery_score, first_score, second_score, confidence, source_type, analysis_notes, revision)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE play_style=VALUES(play_style), pace=VALUES(pace), complexity=VALUES(complexity), primary_game_plan=VALUES(primary_game_plan), win_condition=VALUES(win_condition), resource_engine=VALUES(resource_engine), opening_plan=VALUES(opening_plan), mid_game_plan=VALUES(mid_game_plan), late_game_plan=VALUES(late_game_plan), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_deck_strategy=VALUES(side_deck_strategy), strengths_json=VALUES(strengths_json), weaknesses_json=VALUES(weaknesses_json), strategy_tags_json=VALUES(strategy_tags_json), consistency_score=VALUES(consistency_score), ceiling_score=VALUES(ceiling_score), resilience_score=VALUES(resilience_score), interaction_score=VALUES(interaction_score), recovery_score=VALUES(recovery_score), first_score=VALUES(first_score), second_score=VALUES(second_score), confidence=VALUES(confidence), source_type=VALUES(source_type), analysis_notes=VALUES(analysis_notes), revision=revision+1
        ",
            )
            ->execute([$deckId, ...$values]);
    }

    public function profile(int $deckId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_deck_analysis_profiles WHERE deck_id=? LIMIT 1',
        );
        $stmt->execute([$deckId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function profileHistory(int $deckId, int $revision, array $saved, string $reason): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_deck_analysis_profile_history (deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                $deckId,
                $revision,
                json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $reason,
            ]);
    }

    public function saveMatchup(int $deckId, int $opponentId, array $scope, array $values): void
    {
        $this->pdo
            ->prepare(
                "
            INSERT INTO sx_ygo_matchup_analyses
                (deck_id, opponent_deck_id, deck_version, opponent_version, ruleset_version, strategic_score, classification, reason, key_advantages_json, key_disadvantages_json, choke_points_json, going_first_plan, going_second_plan, side_plan, confidence, source_type, review_notes, revision, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
            ON DUPLICATE KEY UPDATE strategic_score=VALUES(strategic_score), classification=VALUES(classification), reason=VALUES(reason), key_advantages_json=VALUES(key_advantages_json), key_disadvantages_json=VALUES(key_disadvantages_json), choke_points_json=VALUES(choke_points_json), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_plan=VALUES(side_plan), confidence=VALUES(confidence), source_type=VALUES(source_type), review_notes=VALUES(review_notes), revision=revision+1, active=1
        ",
            )
            ->execute([$deckId, $opponentId, ...$scope, ...$values]);
    }

    public function matchup(int $deckId, int $opponentId, array $scope): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_matchup_analyses WHERE deck_id=? AND opponent_deck_id=? AND deck_version=? AND opponent_version=? AND ruleset_version=? LIMIT 1',
        );
        $stmt->execute([$deckId, $opponentId, ...$scope]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function matchupHistory(
        int $analysisId,
        int $deckId,
        int $opponentId,
        int $revision,
        array $analysis,
        string $reason,
    ): void {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_matchup_analysis_history (analysis_id, deck_id, opponent_deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?, ?, ?)',
            )
            ->execute([
                $analysisId,
                $deckId,
                $opponentId,
                $revision,
                json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $reason,
            ]);
    }

    public function audit(string $entity, int $id, string $action, array $payload): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([$entity, $id, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }
}
