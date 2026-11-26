<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Statistics;

use PDO;
use StationX\Yugioh\Application\Statistics\StatisticsPublicationReader;

final readonly class PdoStatisticsPublicationReader implements StatisticsPublicationReader
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,payload,published_at FROM sx_ygo_statistics_publications WHERE id=?',
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
