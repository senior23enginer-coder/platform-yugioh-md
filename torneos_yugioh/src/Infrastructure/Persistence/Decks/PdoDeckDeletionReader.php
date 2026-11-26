<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Decks;

use PDO;
use StationX\Yugioh\Application\Decks\DeckDeletionReader;

final readonly class PdoDeckDeletionReader implements DeckDeletionReader
{
    public function __construct(private PDO $pdo) {}

    public function competition(int $deckId, array $blockedStatuses): ?array
    {
        $placeholders = implode(',', array_fill(0, count($blockedStatuses), '?'));
        $competitionStmt = $this->pdo->prepare("
            SELECT c.id, c.name, c.status, 'seleccionado en evento' AS source
            FROM sx_ygo_competition_decks cd
            JOIN sx_ygo_competitions c ON c.id = cd.competition_id
            WHERE cd.deck_id = ? AND cd.active = 1 AND c.active = 1
              AND LOWER(REPLACE(c.status, ' ', '_')) IN ($placeholders)
            ORDER BY FIELD(LOWER(REPLACE(c.status, ' ', '_')), 'active', 'activo', 'pending_event', 'pendiente_evento', 'pending_configuration', 'pendiente_configuracion', 'completed', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'), c.id DESC
            LIMIT 1
        ");
        $competitionStmt->execute(array_merge([$deckId], $blockedStatuses));
        return $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function match(int $deckId, array $blockedStatuses): ?array
    {
        $placeholders = implode(',', array_fill(0, count($blockedStatuses), '?'));
        $matchStmt = $this->pdo->prepare("
            SELECT c.id, COALESCE(c.name, CONCAT('Combate #', m.id)) AS name, COALESCE(c.status, m.status) AS status, 'programado en combate' AS source
            FROM sx_ygo_matches m
            LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
            WHERE ? IN (m.deck_a_id, m.deck_b_id, m.deck_c_id, m.deck_d_id, m.deck_e_id, m.deck_f_id)
              AND m.status <> 'CANCELADO'
              AND (
                c.id IS NULL
                OR (c.active = 1 AND LOWER(REPLACE(c.status, ' ', '_')) IN ($placeholders))
              )
            ORDER BY m.id DESC
            LIMIT 1
        ");
        $matchStmt->execute(array_merge([$deckId], $blockedStatuses));
        $match = $matchStmt->fetch(PDO::FETCH_ASSOC);
        return $match ?: null;
    }
}
