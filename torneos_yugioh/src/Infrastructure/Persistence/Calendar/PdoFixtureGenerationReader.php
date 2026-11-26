<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\FixtureGenerationReader;

final readonly class PdoFixtureGenerationReader implements FixtureGenerationReader
{
    public function __construct(private PDO $pdo) {}

    public function lockCompetition(array $competition): array
    {
        $fresh = $this->pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');
        $fresh->execute([$competition['id']]);
        return $fresh->fetch(PDO::FETCH_ASSOC) ?: $competition;
    }

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function firstDivisionId(): int
    {
        return \ygo_first_division_id($this->pdo);
    }

    public function generation(int $competitionId, bool $cup): int
    {
        if ($cup) {
            $generationStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = 'competition' AND entity_id = ? AND action = 'configure_competition' AND payload LIKE '%\"mode\":\"schedule_cup\"%'",
            );
            $generationStmt->execute([$competitionId]);
            return ((int) $generationStmt->fetchColumn()) + 1;
        }
        $generationStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = "competition" AND entity_id = ? AND action = "configure_competition"',
        );
        $generationStmt->execute([$competitionId]);
        return ((int) $generationStmt->fetchColumn()) + 1;
    }

    public function hasFinalized(int $competitionId): bool
    {
        $finished = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=? AND status='FINALIZADA'",
        );
        $finished->execute([$competitionId]);
        return (int) $finished->fetchColumn() > 0;
    }
}
