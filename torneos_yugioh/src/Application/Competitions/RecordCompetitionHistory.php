<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\CompetitionLifecyclePolicy;

final readonly class RecordCompetitionHistory
{
    public function __construct(private CompetitionHistoryWriter $writer) {}

    public function execute(
        int $seasonId,
        int $competitionId,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        array $payload = [],
    ): void {
        $change = CompetitionLifecyclePolicy::history($competitionId, $fromStatus, $toStatus);
        if ($change !== null) {
            $this->writer->append(
                $seasonId,
                $competitionId,
                $change['from'],
                $change['to'],
                $action,
                $payload,
            );
        }
    }
}
