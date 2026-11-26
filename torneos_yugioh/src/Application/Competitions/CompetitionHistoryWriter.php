<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionHistoryWriter
{
    public function append(
        int $seasonId,
        int $competitionId,
        ?string $from,
        string $to,
        string $action,
        array $payload,
    ): void;
}
