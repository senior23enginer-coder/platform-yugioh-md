<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamEventRules
{
    public function kind(array $competition): string;

    public function requireOpen(int $seasonId): void;

    public function assertFixtureMutable(int $id): void;

    public function validateFormat(string $format, array $ids): void;

    public function actualFormat(array $ids): string;

    public function validateLeague(int $id, array $ids): void;

    public function hasAdmission(array $competition): bool;

    public function identities(array $ids, string $format): array;

    public function refreshGate(int $id): void;
}
