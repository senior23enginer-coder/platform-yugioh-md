<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamResultStore
{
    public function lockCompetition(int $id): ?array;

    public function lockMatch(int $id): ?array;

    public function load(int $season, int $matchId): ?array;

    public function save(int $season, int $matchId, array $record): void;

    public function saveCollective(int $matchId, array $duels, array $aggregate): void;

    public function audit(int $matchId, array $record): void;
}
