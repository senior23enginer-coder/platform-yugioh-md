<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Settings;

interface AdministrativeStore
{
    public function currentSeasonId(): int;

    public function saveSetting(string $key, string $value): void;

    public function saveLabels(int $seasonId, iterable $rows): void;

    public function saveCatalog(int $seasonId, iterable $rows): void;

    public function addCatalog(int $seasonId, array $row): void;

    public function audit(string $entity, int $id, string $action, array $payload): void;

    public function refreshStatistics(): void;
}
