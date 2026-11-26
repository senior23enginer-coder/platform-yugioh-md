<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

interface StatisticsRevisionStore
{
    public function installed(): bool;

    public function inTransaction(): bool;

    public function underLock(callable $operation): mixed;

    public function policy(): ?string;

    public function publications(bool $full): array;

    public function revision(string $hash): int;

    public function activate(int $revision): void;

    public function previousPayload(): ?string;

    public function persistMedals(array $medals): void;

    public function previousRevision(): ?int;

    public function insertRevision(string $hash, array $data, ?int $previous): array;
}
