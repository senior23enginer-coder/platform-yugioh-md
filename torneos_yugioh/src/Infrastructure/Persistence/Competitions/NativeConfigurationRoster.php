<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionConfigurationRoster;

final readonly class NativeConfigurationRoster implements CompetitionConfigurationRoster
{
    public function __construct(private PDO $pdo) {}

    public function divisions(int $competitionId): array
    {
        return \ygo_league_divisions($this->pdo, $competitionId);
    }

    public function availableLeagueDeckIds(int $competitionId, array $ids): array
    {
        return \ygo_available_league_deck_ids($this->pdo, $competitionId, $ids);
    }

    public function formatDeckIds(string $ruleset): array
    {
        return \ygo_format_deck_ids($this->pdo, $ruleset);
    }

    public function validateFormatRoster(string $ruleset, array $ids): array
    {
        return \ygo_validate_format_roster($this->pdo, $ruleset, $ids);
    }

    public function admissionLoad(array $competition): ?array
    {
        return \ygo_admission_load($this->pdo, $competition);
    }

    public function admissionRequire(array $competition, array $ids, bool $flag): array
    {
        return \ygo_admission_require($this->pdo, $competition, $ids, $flag);
    }

    public function teamKind(array $competition): string
    {
        return \ygo_team_kind($competition);
    }

    public function teamLoad(array $competition): ?array
    {
        return \ygo_team_load($this->pdo, $competition);
    }

    public function rosterGroups(array $ids, int $participantsPerSide): array
    {
        return \ygo_roster_groups_for_match($this->pdo, $ids, $participantsPerSide);
    }

    public function validateLeagueRoster(int $competitionId, array $ids): void
    {
        \ygo_validate_league_roster($this->pdo, $competitionId, $ids);
    }

    public function saveDivisionMovements(int $competitionId, array $input): void
    {
        \ygo_save_division_movements($this->pdo, $competitionId, $input);
    }
}
