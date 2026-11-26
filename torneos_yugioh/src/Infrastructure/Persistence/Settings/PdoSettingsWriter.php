<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Settings;

use PDO;
use StationX\Yugioh\Application\Settings\SettingsWriter;

final readonly class PdoSettingsWriter implements SettingsWriter
{
    public function __construct(private PDO $pdo) {}

    public function save(int $seasonId, string $key, string $value): void
    {
        $this->pdo->prepare('
            INSERT INTO sx_ygo_settings (season_id, setting_key, setting_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ')->execute([$seasonId, $key, $value]);
    }

    public function ensureSettings(int $seasonId, array $defaults): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO sx_ygo_settings (season_id, setting_key, setting_value) VALUES (?, ?, ?)');
        foreach ($defaults as $key => $value) {
            $stmt->execute([$seasonId, $key, $value]);
        }
    }

    public function ensureLabels(int $seasonId, array $defaults): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO sx_ygo_ui_labels (season_id, label_key, title, subtitle, active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE label_key = label_key
        ');
        foreach ($defaults as $label) {
            $stmt->execute([$seasonId, $label[0], $label[1], $label[2]]);
        }
    }
}
