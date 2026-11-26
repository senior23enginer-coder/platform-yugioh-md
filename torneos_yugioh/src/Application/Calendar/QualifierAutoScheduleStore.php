<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Calendar;

interface QualifierAutoScheduleStore
{
    public function pending(?int $competitionId): array;

    public function update(?int $competitionId, array $row): void;

    public function updateDailyLimit(int $competitionId, int $limit): void;

    public function audit(?int $competitionId, array $payload): void;
}
