<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionConfigurationRoster
{
    public function divisions(int $competitionId): array;

    public function availableLeagueDeckIds(int $competitionId, array $ids): array;

    public function formatDeckIds(string $ruleset): array;

    public function validateFormatRoster(string $ruleset, array $ids): array;

    public function admissionLoad(array $competition): ?array;

    public function admissionRequire(array $competition, array $ids, bool $flag): array;

    public function teamKind(array $competition): string;

    public function teamLoad(array $competition): ?array;

    public function rosterGroups(array $ids, int $participantsPerSide): array;

    public function validateLeagueRoster(int $competitionId, array $ids): void;

    public function saveDivisionMovements(int $competitionId, array $input): void;
}
