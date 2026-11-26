<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Settings;

interface SettingsReader
{
    public function settings(int $seasonId): array;

    public function labels(int $seasonId): array;
}
