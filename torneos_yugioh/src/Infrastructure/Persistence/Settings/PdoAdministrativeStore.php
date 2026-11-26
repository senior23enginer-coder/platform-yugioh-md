<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Settings;

use PDO;
use StationX\Yugioh\Application\Settings\AdministrativeStore;

final readonly class PdoAdministrativeStore implements AdministrativeStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function saveSetting(string $key, string $value): void
    {
        \ygo_save_setting($this->pdo, $key, $value);
    }

    public function saveLabels(int $seasonId, iterable $rows): void
    {
        $upsert = $this->pdo->prepare('
            INSERT INTO sx_ygo_ui_labels (season_id, label_key, title, subtitle, active)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), active=VALUES(active)
        ');
        foreach ($rows as $row) {
            $upsert->execute([$seasonId, ...$row]);
        }
    }

    public function saveCatalog(int $seasonId, iterable $rows): void
    {
        $update = $this->pdo->prepare(
            'UPDATE sx_ygo_catalog_items SET label=?, icon=?, accent=?, sort_order=?, active=? WHERE id=? AND season_id IN (0, ?)',
        );
        foreach ($rows as $row) {
            $update->execute([...$row, $seasonId]);
        }
    }

    public function addCatalog(int $seasonId, array $row): void
    {
        $this->pdo->prepare(
            '
            INSERT INTO sx_ygo_catalog_items (season_id, catalog_key, item_code, label, icon, accent, sort_order, meta_json, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, "[]", 1)
            ON DUPLICATE KEY UPDATE label=VALUES(label), icon=VALUES(icon), accent=VALUES(accent), sort_order=VALUES(sort_order), active=1
        ',
        )->execute([$seasonId, ...$row]);
    }

    public function audit(string $entity, int $id, string $action, array $payload): void
    {
        $this->pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute([$entity, $id, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    public function refreshStatistics(): void
    {
        \ygo_sync_meta_history($this->pdo);
        \ygo_sync_elo_history($this->pdo);
        \ygo_sync_global_ranking_history($this->pdo);
        \ygo_sync_event_summaries($this->pdo);
        \ygo_sync_medal_unlocks($this->pdo);
    }
}
