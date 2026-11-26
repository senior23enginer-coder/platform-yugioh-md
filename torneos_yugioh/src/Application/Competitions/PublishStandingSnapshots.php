<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\StandingSnapshotPolicy;

final readonly class PublishStandingSnapshots
{
    public function __construct(
        private StandingSnapshotReader $reader,
        private StandingSnapshotWriter $writer,
    ) {}

    public function execute(int $seasonId, int $competitionId, string $reason = 'current'): void
    {
        if ($competitionId <= 0) {
            return;
        }
        foreach (
            StandingSnapshotPolicy::scopes($this->reader->phases($competitionId))
            as $key => $phase
        ) {
            $standings = $this->reader->standings($competitionId, $phase);
            if (!$standings) {
                $this->writer->clear($competitionId, $key);
                continue;
            }
            $round = $this->reader->maxRound($competitionId, $phase);
            $seen = $this->writer->upsert(
                $seasonId,
                $competitionId,
                $key,
                $round,
                StandingSnapshotPolicy::rows($standings, $reason, $phase),
            );
            if ($seen) {
                $this->writer->deleteAbsent($competitionId, $key, $seen);
            }
        }
    }

    public function backfill(int $seasonId): int
    {
        $ids = $this->reader->missingCompetitionIds($seasonId);
        foreach ($ids as $competitionId) {
            $this->execute($seasonId, $competitionId, 'backfill_missing_snapshot');
        }
        return count($ids);
    }
}
