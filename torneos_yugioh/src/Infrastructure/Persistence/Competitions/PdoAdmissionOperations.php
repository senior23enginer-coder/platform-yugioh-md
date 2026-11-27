<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use DateTimeImmutable;
use StationX\Yugioh\Application\Competitions\AdmissionDirectoryReader;
use StationX\Yugioh\Application\Competitions\AdmissionResultGuardStore;
use StationX\Yugioh\Application\Competitions\AdmissionStageStore;

final readonly class PdoAdmissionOperations implements
    AdmissionDirectoryReader,
    AdmissionResultGuardStore,
    AdmissionStageStore
{
    public function __construct(private PDO $pdo) {}

    public function completedCompetitions(): array
    {
        return $this->pdo
            ->query(
                "SELECT c.*,s.name season_name FROM sx_ygo_competitions c JOIN sx_ygo_seasons s ON s.id=c.season_id WHERE c.active=1 AND c.status IN ('completed','closed','archived') ORDER BY c.season_id,c.name,c.id",
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function divisions(int $id): array
    {
        return \ygo_league_divisions($this->pdo, $id);
    }

    public function competitionForMatch(int $matchId): int
    {
        $q = $this->pdo->prepare('SELECT competition_id FROM sx_ygo_matches WHERE id=?');
        $q->execute([$matchId]);
        return (int) $q->fetchColumn();
    }

    public function lockCompetition(int $source): void
    {
        $lock = $this->pdo->prepare('SELECT id FROM sx_ygo_competitions WHERE id=? FOR UPDATE');
        $lock->execute([$source]);
    }

    public function lockedConfigurations(): array
    {
        return $this->pdo
            ->query(
                "SELECT s.setting_value FROM sx_ygo_settings s JOIN sx_ygo_competitions c ON s.season_id=c.season_id AND s.setting_key=CONCAT('event_admission_',c.id) WHERE c.active=1 FOR UPDATE",
            )
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function pendingIds(array $c, ?string $phase, ?int $round): array
    {
        $q = $this->pdo->prepare(
            "SELECT id FROM sx_ygo_matches WHERE competition_id=? AND status='PENDIENTE'" .
                ($phase !== null ? ' AND phase=? AND round_number=?' : '') .
                ' ORDER BY display_order,id',
        );
        $q->execute($phase !== null ? [$c['id'], $phase, $round] : [$c['id']]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    public function clearDates(array $ids): void
    {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo
            ->prepare(
                "UPDATE sx_ygo_matches SET scheduled_on=NULL,starts_at=NULL WHERE id IN ($marks)",
            )
            ->execute($ids);
    }

    public function lastDate(array $c): string|false|null
    {
        $q = $this->pdo->prepare(
            'SELECT MAX(scheduled_on) FROM sx_ygo_matches WHERE competition_id=?',
        );
        $q->execute([$c['id']]);
        return $q->fetchColumn();
    }

    public function nextDate(string $base, string $pattern, int $index, int $perDay): string
    {
        return \ygo_next_competition_match_date(
            new DateTimeImmutable($base),
            $pattern,
            $index,
            $perDay,
        );
    }

    public function slot(array $c, int $mid, string $date, string $pattern, int $perDay): array
    {
        return \ygo_workflow_slot($this->pdo, $c, $mid, $date, $pattern, $perDay);
    }

    public function updateSlot(int $mid, string $date, string $time): void
    {
        $this->pdo
            ->prepare('UPDATE sx_ygo_matches SET scheduled_on=?,starts_at=? WHERE id=?')
            ->execute([$date, $time, $mid]);
    }

    public function validateSeason(int $id): void
    {
        \ygo_season_validate_competition_schedule($this->pdo, $id);
    }
}
