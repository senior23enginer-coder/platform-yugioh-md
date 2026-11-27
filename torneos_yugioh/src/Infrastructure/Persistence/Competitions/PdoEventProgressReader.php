<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\EventProgressReader;

final class PdoEventProgressReader implements EventProgressReader
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function rounds(): array
    {
        return $this->pdo->query("SELECT competition_id,round_number,COUNT(*) AS total,SUM(CASE WHEN UPPER(TRIM(status)) IN ('FINALIZADA','FINALIZADO','COMPLETED') THEN 1 ELSE 0 END) AS done FROM sx_ygo_matches WHERE competition_id IS NOT NULL GROUP BY competition_id,round_number")->fetchAll(PDO::FETCH_ASSOC);
    }
}
