<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\CompetitionEligibilityReader;

final readonly class PdoCompetitionEligibilityReader implements CompetitionEligibilityReader
{
    public function __construct(private PDO $pdo) {}

    public function activeDeckIds(?int $divisionId = null): array
    {
        if ($divisionId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL AND division_id = ?',
            );
            $stmt->execute([$divisionId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        $stmt = $this->pdo->query(
            'SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL',
        );
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function competition(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1',
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function admissionSelection(array $competition): ?array
    {
        if (
            \ygo_admission_required($competition) ||
            \ygo_admission_load($this->pdo, $competition) !== null
        ) {
            $admission = \ygo_admission_view($this->pdo, $competition);
            return $admission['ok'] ? $admission['selection']['ids'] ?? [] : [];
        }
        return null;
    }

    public function formatDeckIds(string $ruleset): array
    {
        return \ygo_format_deck_ids($this->pdo, $ruleset);
    }

    public function registeredDeckIds(int $competitionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1 ORDER BY sort_order, id',
        );
        $stmt->execute([$competitionId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function divisionForRuleset(string $ruleset): int
    {
        $code = \ygo_ruleset_division_code($ruleset);
        if (!$code) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id FROM sx_ygo_divisions WHERE code = ? AND active = 1 ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute([$code]);
        return (int) $stmt->fetchColumn();
    }

    public function dependencies(int $competitionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT depends_on_competition_id, condition_type, take_count
            FROM sx_ygo_competition_dependencies
            WHERE competition_id = ? AND active = 1
            ORDER BY id
        ");
        $stmt->execute([$competitionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function standings(int $competitionId): array
    {
        return \ygo_competition_standings($this->pdo, $competitionId);
    }

    public function participantsWithResults(array $requiredIds): array
    {
        if (!$requiredIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($requiredIds), '?'));
        $qualifiedStmt = $this->pdo->prepare("
            SELECT DISTINCT deck_id FROM (
                SELECT m.deck_a_id AS deck_id
                FROM sx_ygo_matches m
                JOIN sx_ygo_match_results r ON r.match_id = m.id
                WHERE m.competition_id IN ($placeholders) AND r.result IS NOT NULL AND r.result <> ''
                UNION
                SELECT m.deck_b_id AS deck_id
                FROM sx_ygo_matches m
                JOIN sx_ygo_match_results r ON r.match_id = m.id
                WHERE m.competition_id IN ($placeholders) AND r.result IS NOT NULL AND r.result <> ''
                UNION
                SELECT m.deck_c_id AS deck_id
                FROM sx_ygo_matches m
                JOIN sx_ygo_match_results r ON r.match_id = m.id
                WHERE m.competition_id IN ($placeholders) AND m.deck_c_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                UNION
                SELECT m.deck_d_id AS deck_id
                FROM sx_ygo_matches m
                JOIN sx_ygo_match_results r ON r.match_id = m.id
                WHERE m.competition_id IN ($placeholders) AND m.deck_d_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                UNION
                SELECT m.deck_e_id AS deck_id
                FROM sx_ygo_matches m
                JOIN sx_ygo_match_results r ON r.match_id = m.id
                WHERE m.competition_id IN ($placeholders) AND m.deck_e_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                UNION
                SELECT m.deck_f_id AS deck_id
                FROM sx_ygo_matches m
                JOIN sx_ygo_match_results r ON r.match_id = m.id
                WHERE m.competition_id IN ($placeholders) AND m.deck_f_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
            ) qualified
        ");
        $qualifiedStmt->execute(
            array_merge(
                $requiredIds,
                $requiredIds,
                $requiredIds,
                $requiredIds,
                $requiredIds,
                $requiredIds,
            ),
        );
        return array_map('intval', $qualifiedStmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
