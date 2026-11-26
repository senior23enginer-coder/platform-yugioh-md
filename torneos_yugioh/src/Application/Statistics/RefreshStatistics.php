<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Application\Shared\TransactionBoundary;
use StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy;

final readonly class RefreshStatistics
{
    public function __construct(
        private StatisticsRevisionStore $store,
        private ReadStatisticsSource $source,
        private CalculateStatistics $calculator,
        private TransactionBoundary $transaction,
        private Clock $clock,
    ) {}

    public function execute(): int
    {
        if (!$this->store->installed() || $this->store->inTransaction()) {
            return 0;
        }
        return $this->store->underLock(fn(): int => $this->transaction->run(function (): int {
            $source = $this->source->execute();
            $policy = StatisticsRevisionPolicy::policy($this->store->policy());
            $source = StatisticsRevisionPolicy::fixedBonuses($source, $policy);
            $cuts = $this->store->publications(false);
            $tiers = StatisticsRevisionPolicy::publishedTiers($cuts);
            $hash = StatisticsRevisionPolicy::sourceHash($source, $policy, $cuts, $this->clock->now()->format('Y-m-d'));
            $revision = $this->store->revision($hash);
            if ($revision) {
                $this->store->activate($revision);
                return 0;
            }
            $data = $this->calculator->execute($source['matches'], $source['events'], $source['honors'], $policy, null, $tiers, $source['ffa_matches']);
            $data['source_hash'] = $hash;
            $data = StatisticsRevisionPolicy::preserveRevocations($data, $this->store->previousPayload());
            $this->store->persistMedals($data['medals']);
            $inserted = $this->store->insertRevision($hash, $data, $this->store->previousRevision());
            $this->store->activate($inserted['id']);
            return $inserted['count'];
        }));
    }
}
