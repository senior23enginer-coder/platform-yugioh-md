<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionMaintenanceServices;
use StationX\Yugioh\Infrastructure\Composition\QualifierFinalizationModule;

final readonly class NativeCompetitionMaintenanceServices implements CompetitionMaintenanceServices
{
    public function __construct(private PDO $pdo) {}

    public function selectedQualifier(int $postedId): ?int
    {
        return QualifierFinalizationModule::create($this->pdo)->select($postedId);
    }

    public function finalizeQualifier(?int $id, int $postedId): bool
    {
        return QualifierFinalizationModule::create($this->pdo)->execute($id, $postedId);
    }

    public function history(int $seasonId, int $id, string $from, string $to, string $action, array $payload = []): void
    {
        \ygo_record_competition_status_history($this->pdo, $seasonId, $id, $from, $to, $action, $payload);
    }

    public function standings(int $seasonId, int $id, string $reason): void
    {
        \ygo_persist_competition_standings($this->pdo, $seasonId, $id, $reason);
    }

    public function applyDivision(int $id): int
    {
        return \ygo_apply_division_from_qualifier($this->pdo, $id);
    }

    public function leagueMovements(int $id): int
    {
        return \ygo_apply_league_division_movements($this->pdo, $id);
    }

    public function honor(int $id): int
    {
        return \ygo_record_competition_honor($this->pdo, $id);
    }

    public function readiness(int $id): array
    {
        return \ygo_competition_ready_to_finish($this->pdo, $id);
    }
}
