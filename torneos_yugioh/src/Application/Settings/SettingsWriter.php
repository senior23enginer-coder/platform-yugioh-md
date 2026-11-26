<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Settings;

interface SettingsWriter
{
    public function save(int $seasonId, string $key, string $value): void;

    public function ensureSettings(int $seasonId, array $defaults): void;

    public function ensureLabels(int $seasonId, array $defaults): void;
}
