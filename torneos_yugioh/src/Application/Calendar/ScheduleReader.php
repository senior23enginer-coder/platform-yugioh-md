<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

/** Native active/status predicates and ordering are implemented by the outgoing storage adapter. */
interface ScheduleReader
{
    public function activeCompetition(int $competitionId): ?array;
    /** @return list<int> */
    public function pendingMatchIds(int $competitionId): array;
    public function occupancy(int $excludeCompetitionId): array;
    public function blackouts(int $competitionId): array;
    public function competitionType(int $competitionId): string;
}
