<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\GoatLeagueStore;

final readonly class PdoGoatLeagueStore implements GoatLeagueStore
{
    public function __construct(private PDO $pdo) {}

    public function legacySeasonId(): int
    {
        return (int) $this->pdo->query("SELECT id FROM sx_ygo_seasons WHERE slug='temporada-2026'")->fetchColumn();
    }

    public function division(int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, league_competition_id FROM sx_ygo_divisions WHERE season_id=? AND code="GOAT" AND active=1 LIMIT 1',
        );
        $stmt->execute([$seasonId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function ensureDivision(int $seasonId): void
    {
        $this->pdo->prepare('
            INSERT INTO sx_ygo_divisions
                (season_id, code, name, cup_name, tier_order, deck_count, accent, description, active)
            VALUES
                (?, "GOAT", "Primera Division GOAT", "Copa GOAT", 3, 0, "#36d8ff", "Primera division de la Liga GOAT para partidas retro y control de recursos.", 1)
            ON DUPLICATE KEY UPDATE
                name=VALUES(name), cup_name=VALUES(cup_name), accent=VALUES(accent), description=VALUES(description), active=1
        ')->execute([$seasonId]);
    }

    public function leagueId(int $seasonId): int
    {
        $leagueStmt = $this->pdo->prepare('
            SELECT id
            FROM sx_ygo_competitions
            WHERE season_id=? AND active=1 AND type="league"
              AND (LOWER(COALESCE(event_ruleset, ""))="goat" OR LOWER(name) LIKE "%goat%")
            ORDER BY (code="liga-goat") DESC, id DESC
            LIMIT 1
        ');
        $leagueStmt->execute([$seasonId]);
        return (int) ($leagueStmt->fetchColumn() ?: 0);
    }

    public function link(int $seasonId, int $divisionId, int $leagueId): int
    {
        $leagueUpdate = $this->pdo->prepare('UPDATE sx_ygo_competitions SET division_id=?, event_ruleset="goat", deck_pool="division" WHERE id=? AND season_id=?');
        $leagueUpdate->execute([$divisionId, $leagueId, $seasonId]);
        $divisionUpdate = $this->pdo->prepare('UPDATE sx_ygo_divisions SET league_competition_id=? WHERE id=? AND season_id=?');
        $divisionUpdate->execute([$leagueId, $divisionId, $seasonId]);
        return max($leagueUpdate->rowCount(), $divisionUpdate->rowCount());
    }

    public function qualifierIds(int $seasonId): array
    {
        $qualifierStmt = $this->pdo->prepare('
            SELECT id
            FROM sx_ygo_competitions
            WHERE season_id=? AND active=1 AND type="qualifier"
              AND (LOWER(COALESCE(event_ruleset, ""))="goat" OR LOWER(name) LIKE "%goat%")
        ');
        $qualifierStmt->execute([$seasonId]);
        return array_values(array_filter(array_map('intval', $qualifierStmt->fetchAll(PDO::FETCH_COLUMN)), static fn(int $id): bool => $id > 0));
    }

    public function disableQualifiers(array $qualifierIds): void
    {
        if (!$qualifierIds) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($qualifierIds), '?'));
        $this->pdo->prepare("UPDATE sx_ygo_competitions SET active=0 WHERE id IN ($placeholders)")->execute($qualifierIds);
        $this->pdo->prepare("UPDATE sx_ygo_events SET active=0 WHERE competition_id IN ($placeholders)")->execute($qualifierIds);
        $this->pdo->prepare("UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id IN ($placeholders) OR depends_on_competition_id IN ($placeholders)")
            ->execute([...$qualifierIds, ...$qualifierIds]);
    }

    public function audit(int $divisionId, int $leagueId, array $qualifierIds): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $leagueId ?: null, 'ensure_goat_league_structure', json_encode([
                'division_id' => $divisionId,
                'league_id' => $leagueId,
                'disabled_qualifier_ids' => $qualifierIds,
            ], JSON_UNESCAPED_UNICODE)]);
    }
}
