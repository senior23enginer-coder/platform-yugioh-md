<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Commands;

use StationX\Yugioh\Application\Shared\TransactionBoundary;

final readonly class ArchiveQaData
{
    public function __construct(private QaArchiveStore $store, private TransactionBoundary $transaction) {}

    public function execute(): string
    {
        $seasonId = $this->store->currentSeasonId();
        return $this->transaction->run(function () use ($seasonId): string {
            $counts = $this->store->archive($seasonId);
            $this->store->audit($seasonId, $counts);
            return 'qa_codex_archivado_' . array_sum($counts);
        });
    }
}
