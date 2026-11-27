<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface AdmissionWriteStore
{
    public function lockedCompetition(int $target): array;

    public function requireOpen(int $seasonId): void;

    public function assertMutable(int $target): void;

    public function normalizedStatus(string $status): string;

    public function assertNoCycle(int $target, array $sources): void;

    public function resolve(array $competition, array $admission): array;

    public function validateRoster(int $target, array $ids): void;

    public function replaceDecks(int $target, array $ids): void;

    public function clearDependencies(int $target): void;

    public function writeDependencies(array $competition, int $target, array $dependencies): void;

    public function setParticipantSource(int $target): void;

    public function store(array $competition, array $admission): void;

    public function refreshGate(int $target): void;

    public function audit(int $target, array $admission): void;
}
