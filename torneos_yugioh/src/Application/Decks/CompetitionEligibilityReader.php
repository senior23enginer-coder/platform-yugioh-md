<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface CompetitionEligibilityReader
{
    public function activeDeckIds(?int $divisionId = null): array;

    public function competition(int $id): ?array;

    /** Null means the legacy pool rules apply; an empty array means admission denied. */
    public function admissionSelection(array $competition): ?array;

    public function formatDeckIds(string $ruleset): array;

    public function registeredDeckIds(int $competitionId): array;

    public function divisionForRuleset(string $ruleset): int;

    public function dependencies(int $competitionId): array;

    public function standings(int $competitionId): array;

    public function participantsWithResults(array $competitionIds): array;
}
