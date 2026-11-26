<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionLifecycleReader;

final readonly class PdoCompetitionLifecycleReader implements CompetitionLifecycleReader
{
    public function __construct(private PDO $pdo) {}

    public function workflow(int $competitionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');
        $stmt->execute([$competitionId]);
        $competition = $stmt->fetch(PDO::FETCH_ASSOC);
        return $competition ? \ygo_workflow_load($this->pdo, $competition) : null;
    }

    public function matchCounts(int $competitionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_matches,
                SUM(CASE WHEN status = 'FINALIZADA' THEN 1 ELSE 0 END) AS finalized_matches
            FROM sx_ygo_matches
            WHERE competition_id = ?
        ");
        $stmt->execute([$competitionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
