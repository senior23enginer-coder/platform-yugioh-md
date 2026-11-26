<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface MatchScheduleStore
{
    public function find(int $matchId): array;

    public function update(int $matchId, array $values): void;

    public function audit(int $matchId, string $action, array $payload): void;

    public function competitionType(int $competitionId): string;
}
