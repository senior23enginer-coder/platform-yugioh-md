<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Statistics;

use StationX\Yugioh\Domain\Statistics\GlobalPolicyConfiguration;

final readonly class ConfigureGlobalStatistics
{
    public function __construct(private GlobalPolicyStore $store) {}

    public function execute(string $anchor, array $mapping = []): void
    {
        $policy = GlobalPolicyConfiguration::build($anchor, $mapping);
        GlobalPolicyConfiguration::assertUnchanged($this->store->policy(), $policy);
        $this->store->save($policy);
    }

    public function bootstrap(): void
    {
        if (!$this->store->inTransaction() && !$this->store->exists()) {
            $this->execute('2026-10-18', ['FULL' => 20, 'REDUCED' => 20, 'BONUS' => 25]);
        }
    }
}
