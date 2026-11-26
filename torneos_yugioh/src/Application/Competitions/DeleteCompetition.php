<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Competitions\CompetitionDeletionPolicy;

final readonly class DeleteCompetition
{
    public function __construct(
        private CompetitionDeletionStore $store,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(int $competitionId, string $confirmation): int
    {
        if (!CompetitionDeletionPolicy::confirmed($competitionId, $confirmation)) {
            return 0;
        }
        $seasonId = $this->store->currentSeasonId();
        $competition = $this->store->competition($seasonId, $competitionId);
        $status = CompetitionDeletionPolicy::deletableStatus($competition);
        if ($status === null) {
            return 0;
        }
        return $this->transaction->run(function () use ($seasonId, $competitionId, $status): int {
            $this->store->delete($seasonId, $competitionId, $status);
            return $competitionId;
        });
    }
}
