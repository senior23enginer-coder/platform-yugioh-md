<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final readonly class RunHistoricalBackfills
{
    public function __construct(private HistoricalBackfillPort $port) {}

    public function execute(): void
    {
        if (!$this->port->completed('medal_backfill_v2')) {
            $this->port->record('medal_backfill_v2', [
                'unlocks_processed' => $this->port->syncMedals(),
            ]);
        }

        if (!$this->port->completed('snapshot_backfill_v1')) {
            $this->port->record('snapshot_backfill_v1', [
                'snapshots_inferred' => $this->port->backfillSnapshots(),
            ]);
        }

        if (!$this->port->completed('historical_intelligence_v2')) {
            $meta = $this->port->syncMeta();
            $elo = $this->port->syncElo();
            $this->port->syncRanking();
            $this->port->syncSummaries();
            $this->port->syncMedals();
            $this->port->record('historical_intelligence_v2', [
                'meta_rows' => $meta,
                'elo_rows' => $elo,
            ]);
        }
    }
}
