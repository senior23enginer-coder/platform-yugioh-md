<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\ManualHonorStore;

final readonly class PdoManualHonorStore implements ManualHonorStore
{
    public function __construct(private PDO $pdo) {}

    public function currentSeasonId(): int
    {
        return \ygo_current_season_id($this->pdo);
    }

    public function lockEvent(int $eventId, int $seasonId): array
    {
        $q = $this->pdo->prepare(
            'SELECT * FROM sx_ygo_competitions WHERE id=? AND season_id=? FOR UPDATE',
        );
        $q->execute([$eventId, $seasonId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function hasHonor(int $eventId): bool
    {
        $q = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id=?');
        $q->execute([$eventId]);
        return (bool) $q->fetchColumn();
    }

    public function participants(int $eventId): array
    {
        $q = $this->pdo->prepare(
            'SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 UNION SELECT deck_a_id FROM sx_ygo_matches WHERE competition_id=? UNION SELECT deck_b_id FROM sx_ygo_matches WHERE competition_id=?',
        );
        $q->execute([$eventId, $eventId, $eventId]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    public function validateRoster(string $ruleset, array $ids): void
    {
        \ygo_validate_format_roster($this->pdo, $ruleset, $ids);
    }

    public function team(int $deck): int
    {
        $q = $this->pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=?');
        $q->execute([$deck]);
        return (int) $q->fetchColumn();
    }

    public function record(array $values): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_honors(season_id,competition_id,champion_deck_id,champion_team_id,runner_up_deck_id,runner_up_team_id,title,notes) VALUES(?,?,?,?,?,?,?,?)',
            )
            ->execute($values);
    }

    public function audit(int $eventId, int $champion, int $team, string $notes): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )
            ->execute([
                'competition',
                $eventId,
                'manual_honor_confirmed',
                json_encode(
                    [
                        'champion_deck_id' => $champion,
                        'champion_team_id' => $team,
                        'evidence' => $notes,
                    ],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
    }
}
