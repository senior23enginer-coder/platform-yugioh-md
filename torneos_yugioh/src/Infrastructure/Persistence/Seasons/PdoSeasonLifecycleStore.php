<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Seasons;

use PDO;
use StationX\Yugioh\Application\Seasons\SeasonLifecycleStore;

final readonly class PdoSeasonLifecycleStore implements SeasonLifecycleStore
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id, bool $lock = false): ?array
    {
        return (new PdoSeasonScheduleReader($this->pdo))->find($id, $lock);
    }

    public function hasOutsideDates(int $id, string $start, string $end): bool
    {
        $outside = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sx_ygo_competitions WHERE season_id=? AND ((starts_on IS NOT NULL AND (starts_on<? OR starts_on>?)) OR (ends_on IS NOT NULL AND (ends_on<? OR ends_on>?)))',
        );
        $outside->execute([$id, $start, $end, $start, $end]);
        $matches = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE c.season_id=? AND m.scheduled_on IS NOT NULL AND (m.scheduled_on<? OR m.scheduled_on>?)',
        );
        $matches->execute([$id, $start, $end]);
        return (int) $outside->fetchColumn() > 0 || (int) $matches->fetchColumn() > 0;
    }

    public function insert(array $definition): int
    {
        $name = $definition['name'];
        $slug = strtolower(
            trim(
                preg_replace(
                    '/[^a-zA-Z0-9]+/',
                    '-',
                    iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name,
                ),
                '-',
            ),
        );
        $slug = substr($slug ?: 'temporada', 0, 56) . '-' . bin2hex(random_bytes(5));
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_seasons(name,slug,status,period_type,starts_on,ends_on) VALUES(?,?,?,?,?,?)',
            )
            ->execute([
                $name,
                $slug,
                $definition['status'],
                $definition['period_type'],
                $definition['starts_on'],
                $definition['ends_on'],
            ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $definition): void
    {
        $this->pdo
            ->prepare(
                'UPDATE sx_ygo_seasons SET name=?,period_type=?,starts_on=?,ends_on=?,status=? WHERE id=?',
            )
            ->execute([
                $definition['name'],
                $definition['period_type'],
                $definition['starts_on'],
                $definition['ends_on'],
                $definition['status'],
                $id,
            ]);
    }

    public function openCompetitions(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id,status FROM sx_ygo_competitions WHERE season_id=? AND status NOT IN ('completed','cancelled','closed','archived') FOR UPDATE",
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function closeCompetition(int $seasonId, array $competition, string $reason): void
    {
        $this->pdo
            ->prepare(
                "UPDATE sx_ygo_competitions SET status='closed',configuration_locked=1 WHERE id=?",
            )
            ->execute([$competition['id']]);
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_competition_status_history(season_id,competition_id,from_status,to_status,action,payload) VALUES(?,?,?,?,?,?)',
            )
            ->execute([
                $seasonId,
                $competition['id'],
                $competition['status'],
                'closed',
                'season_closed',
                json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE),
            ]);
    }

    public function completeSeason(int $id): void
    {
        $this->pdo
            ->prepare(
                "UPDATE sx_ygo_events SET status='closed' WHERE season_id=? AND status NOT IN ('completed','cancelled','closed')",
            )
            ->execute([$id]);
        $this->pdo
            ->prepare("UPDATE sx_ygo_seasons SET status='completed',closed_at=NOW() WHERE id=?")
            ->execute([$id]);
    }

    public function expiredIds(string $today): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM sx_ygo_seasons WHERE ends_on<? AND status NOT IN ('completed','archived')",
        );
        $stmt->execute([$today]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function hasDependencies(int $id): bool
    {
        foreach (['sx_ygo_competitions', 'sx_ygo_divisions', 'sx_ygo_events'] as $table) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE season_id=?');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }
        return false;
    }

    public function deleteSeason(int $id): void
    {
        $this->pdo
            ->prepare(
                'DELETE gc FROM sx_ygo_game_rule_conditions gc JOIN sx_ygo_game_rules g ON g.id=gc.game_rule_id WHERE g.season_id=?',
            )
            ->execute([$id]);
        foreach (
            [
                'sx_ygo_game_rules',
                'sx_ygo_ruleset_versions',
                'sx_ygo_rulesets',
                'sx_ygo_settings',
                'sx_ygo_ui_labels',
                'sx_ygo_catalog_items',
            ]
            as $table
        ) {
            $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE season_id=?')->execute([$id]);
        }
        $this->pdo->prepare('DELETE FROM sx_ygo_seasons WHERE id=?')->execute([$id]);
    }

    public function audit(int $id, string $action, array $payload): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )
            ->execute([
                'season',
                $id,
                $action,
                json_encode((object) $payload, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
