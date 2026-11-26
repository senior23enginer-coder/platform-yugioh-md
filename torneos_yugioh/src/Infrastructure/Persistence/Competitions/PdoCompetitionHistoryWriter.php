<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionHistoryWriter;

final readonly class PdoCompetitionHistoryWriter implements CompetitionHistoryWriter
{
    public function __construct(private PDO $pdo) {}

    public function append(
        int $seasonId,
        int $competitionId,
        ?string $from,
        string $to,
        string $action,
        array $payload,
    ): void {
        $this->pdo
            ->prepare(
                '
            INSERT INTO sx_ygo_competition_status_history (season_id, competition_id, from_status, to_status, action, payload)
            VALUES (?, ?, ?, ?, ?, ?)
        ',
            )
            ->execute([
                $seasonId,
                $competitionId,
                $from,
                $to,
                $action,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
