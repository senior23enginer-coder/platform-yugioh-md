<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Commands\QaArchiveStore;

final readonly class PdoQaArchiveStore implements QaArchiveStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function archive(int $seasonId): array
    {
        $counts = [];
        $stmt = $this->pdo->prepare(
            "UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE season_id=? AND name LIKE 'QA Codex %' AND name <> 'Clasificatorio inicial MD'",
        );
        $stmt->execute([$seasonId]);
        $counts['competitions'] = $stmt->rowCount();
        $stmt = $this->pdo->prepare(
            "UPDATE sx_ygo_divisions SET active=0 WHERE season_id=? AND name LIKE 'QA Codex %'",
        );
        $stmt->execute([$seasonId]);
        $counts['divisions'] = $stmt->rowCount();
        $stmt = $this->pdo->prepare(
            "UPDATE sx_ygo_decks SET active=0 WHERE name LIKE 'QA Codex %'",
        );
        $stmt->execute();
        $counts['decks'] = $stmt->rowCount();
        $stmt = $this->pdo->prepare(
            "UPDATE sx_ygo_teams SET active=0 WHERE name LIKE 'QA Codex %'",
        );
        $stmt->execute();
        $counts['teams'] = $stmt->rowCount();
        return $counts;
    }

    public function audit(int $seasonId, array $counts): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)',
            )
            ->execute([
                'qa',
                $seasonId,
                'archive_qa_codex_data',
                json_encode($counts, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
