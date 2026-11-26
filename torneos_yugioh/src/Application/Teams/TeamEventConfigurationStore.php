<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamEventConfigurationStore
{
    public function loadJson(array $competition): ?string;

    public function storeJson(array $competition, string $json): void;

    public function lockCompetition(int $id): ?array;

    public function replaceRoster(int $id, array $ids): void;

    public function audit(int $id, array $state): void;

    public function bindMatch(int $competitionId, int $matchId, array $binding): void;
}
