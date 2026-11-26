<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Calendar;

use PDO;
use StationX\Yugioh\Application\Calendar\MatchScheduleValidationReader;
use StationX\Yugioh\Application\Calendar\ResultDateReader;

final readonly class PdoMatchScheduleValidationReader implements
    ResultDateReader,
    MatchScheduleValidationReader
{
    public function __construct(private PDO $pdo) {}

    public function settings(): array
    {
        return \ygo_settings($this->pdo);
    }

    public function match(int $matchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, competition_id, scheduled_on, starts_at, status FROM sx_ygo_matches WHERE id = ? LIMIT 1',
        );
        $stmt->execute([$matchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function gate(int $competitionId): array
    {
        return \ygo_competition_gate($this->pdo, $competitionId);
    }

    public function participants(int $matchId, array $explicitIds): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT deck_a_id,deck_b_id,deck_c_id,deck_d_id,deck_e_id,deck_f_id FROM sx_ygo_matches WHERE id=?',
        );
        $stmt->execute([$matchId]);
        return $explicitIds ?: ($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    public function freeForAll(string $date): array
    {
        if (!\ygo_table_exists($this->pdo, 'sx_ygo_ffa_matches')) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT state_json,status FROM sx_ygo_ffa_matches WHERE scheduled_on=?',
        );
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function availableFrom(array $deckIds): string
    {
        return \ygo_deck_available_from($this->pdo, $deckIds);
    }

    public function busyMatches(string $date, int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT deck_a_id,deck_b_id,deck_c_id,deck_d_id,deck_e_id,deck_f_id FROM sx_ygo_matches WHERE scheduled_on=? AND id<>?',
        );
        $stmt->execute([$date, $matchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pendingCount(string $date, int $matchId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sx_ygo_matches WHERE scheduled_on = ? AND id <> ? AND status <> 'FINALIZADA'",
        );
        $stmt->execute([$date, $matchId]);
        return (int) $stmt->fetchColumn();
    }

    public function canDisplaceQualifier(int $matchId, string $date): bool
    {
        return \ygo_schedule_can_displace_qualifier($this->pdo, $matchId, $date);
    }
}
