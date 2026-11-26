<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Teams;

use PDO;
use StationX\Yugioh\Application\Teams\TeamEventRules;

final readonly class NativeTeamEventRules implements TeamEventRules
{
    public function __construct(private PDO $pdo) {}

    public function kind(array $competition): string
    {
        return \ygo_team_kind($competition);
    }

    public function requireOpen(int $seasonId): void
    {
        \ygo_season_require_open($this->pdo, $seasonId);
    }

    public function assertFixtureMutable(int $id): void
    {
        \ygo_assert_fixture_mutable($this->pdo, $id);
    }

    public function validateFormat(string $format, array $ids): void
    {
        \ygo_validate_format_roster($this->pdo, $format, $ids);
    }

    public function actualFormat(array $ids): string
    {
        return \ygo_admission_deck_format($this->pdo, $ids);
    }

    public function validateLeague(int $id, array $ids): void
    {
        \ygo_validate_league_roster($this->pdo, $id, $ids);
    }

    public function hasAdmission(array $competition): bool
    {
        return \ygo_admission_required($competition) || \ygo_admission_load($this->pdo, $competition) !== null;
    }

    public function identities(array $ids, string $format): array
    {
        return \ygo_participant_identities($this->pdo, $ids, $format);
    }

    public function refreshGate(int $id): void
    {
        \ygo_competition_gate($this->pdo, $id, true);
    }
}
