<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Statistics;

use PDO;
use StationX\Yugioh\Application\Statistics\GlobalPolicyStore;
use StationX\Yugioh\Domain\Statistics\StatisticsPolicy;

final readonly class PdoGlobalPolicyStore implements GlobalPolicyStore
{
    public function __construct(private PDO $pdo) {}

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function exists(): bool
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM sx_ygo_statistics_policy WHERE scope_key='global'")->fetchColumn() > 0;
    }

    public function policy(): ?string
    {
        $value = $this->pdo->query("SELECT payload FROM sx_ygo_statistics_policy WHERE scope_key='global'")->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function save(array $policy): void
    {
        $this->pdo->prepare("INSERT IGNORE INTO sx_ygo_statistics_policy(scope_key,version,payload) VALUES('global',?,?)")->execute([StatisticsPolicy::VERSION, json_encode($policy)]);
    }
}
