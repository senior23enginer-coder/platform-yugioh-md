<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Settings;

use PDO;
use StationX\Yugioh\Application\Settings\SettingsReader;

final readonly class PdoSettingsReader implements SettingsReader
{
    public function __construct(private PDO $pdo) {}

    public function settings(int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT setting_key, setting_value FROM sx_ygo_settings WHERE season_id = ?',
        );
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function labels(int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_ui_labels WHERE season_id IN (0, ?) ORDER BY label_key',
        );
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
