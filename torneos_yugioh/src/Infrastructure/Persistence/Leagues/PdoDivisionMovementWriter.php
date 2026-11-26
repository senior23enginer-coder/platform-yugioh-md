<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Leagues;

use PDO;
use StationX\Yugioh\Application\Leagues\DivisionMovementWriter;

final readonly class PdoDivisionMovementWriter implements DivisionMovementWriter
{
    public function __construct(private PDO $pdo) {}

    public function saveConfiguration(int $competitionId, array $rows): void
    {
        $update = $this->pdo->prepare(
            'UPDATE sx_ygo_divisions SET promotion_slots=?,promotion_to_division_id=?,relegation_slots=?,relegation_to_division_id=? WHERE id=? AND league_competition_id=?',
        );
        foreach ($rows as $row) {
            $update->execute([
                $row['promotion_slots'],
                $row['promotion_to_division_id'],
                $row['relegation_slots'],
                $row['relegation_to_division_id'],
                $row['id'],
                $competitionId,
            ]);
        }
    }

    public function apply(int $competitionId, array $moves): void
    {
        $update = $this->pdo->prepare(
            'UPDATE sx_ygo_decks SET division_id=? WHERE id=? AND division_id=?',
        );
        $audit = $this->pdo->prepare(
            'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
        );
        foreach ($moves as $move) {
            $update->execute([
                $move['to_division_id'],
                $move['deck_id'],
                $move['from_division_id'],
            ]);
            $audit->execute([
                'deck',
                $move['deck_id'],
                'division_' . $move['kind'],
                json_encode(['competition_id' => $competitionId] + $move),
            ]);
        }
        $this->pdo->prepare(
            'UPDATE sx_ygo_divisions dv SET deck_count=(SELECT COUNT(*) FROM sx_ygo_decks d WHERE d.division_id=dv.id AND d.active=1) WHERE league_competition_id=?',
        )->execute([$competitionId]);
        $audit->execute([
            'competition',
            $competitionId,
            'division_movements_applied',
            json_encode(['moves' => $moves]),
        ]);
    }
}
