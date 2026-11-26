<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface MatchResultStore
{
    public function syncedResult(string $requestId): string|false;

    public function audit(int $matchId, string $action, array $payload): void;

    public function ensure(int $matchId): void;

    public function lockExisting(int $matchId): array;

    public function persistedTurns(int $matchId): array;

    public function appendTurns(int $matchId, iterable $rows): void;

    public function save(array $values): void;

    public function competitionId(int $matchId): int;

    public function saveLiveState(int $matchId, array $state): void;

    public function recordSync(string $requestId, int $matchId, string $payloadHash): void;
}
