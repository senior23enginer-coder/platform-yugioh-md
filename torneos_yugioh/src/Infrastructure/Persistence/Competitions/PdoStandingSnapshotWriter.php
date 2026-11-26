<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\StandingSnapshotWriter;

final readonly class PdoStandingSnapshotWriter implements StandingSnapshotWriter
{
    public function __construct(private PDO $pdo) {}

    public function clear(int $competitionId, string $snapshotKey): void
    {
        $this->pdo
            ->prepare(
                'DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ? AND snapshot_key = ?',
            )
            ->execute([$competitionId, $snapshotKey]);
    }

    public function upsert(
        int $seasonId,
        int $competitionId,
        string $snapshotKey,
        int $round,
        iterable $rows,
    ): array {
        $upsert = $this->pdo->prepare('
            INSERT INTO sx_ygo_standing_snapshots
                (season_id, competition_id, snapshot_key, round_number, deck_id, position, points, played, wins, draws, losses, duels_won, duels_lost, difference, payload)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                round_number=VALUES(round_number),
                position=VALUES(position),
                points=VALUES(points),
                played=VALUES(played),
                wins=VALUES(wins),
                draws=VALUES(draws),
                losses=VALUES(losses),
                duels_won=VALUES(duels_won),
                duels_lost=VALUES(duels_lost),
                difference=VALUES(difference),
                payload=VALUES(payload)
        ');
        $seenDeckIds = [];
        foreach ($rows as $row) {
            $seenDeckIds[] = $row[0];
            $payload = array_pop($row);
            $upsert->execute([
                $seasonId,
                $competitionId,
                $snapshotKey,
                $round,
                ...$row,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        }
        return $seenDeckIds;
    }

    public function deleteAbsent(int $competitionId, string $snapshotKey, array $seenDeckIds): void
    {
        $placeholders = implode(',', array_fill(0, count($seenDeckIds), '?'));
        $this->pdo
            ->prepare(
                "DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ? AND snapshot_key = ? AND deck_id NOT IN ($placeholders)",
            )
            ->execute(array_merge([$competitionId, $snapshotKey], $seenDeckIds));
    }
}
