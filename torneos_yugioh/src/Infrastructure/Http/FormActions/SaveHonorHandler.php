<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use Throwable;
use InvalidArgumentException;

/** Native save_honor adapter. Keeps its transaction and audit boundaries while legacy services migrate. */
final class SaveHonorHandler extends NativeFormHandler
{
    protected function perform(PDO $pdo, string $action, array $post, array $files): ?string
    {
        $seasonId = ygo_current_season_id($pdo);
        $eventId = max(0, (int) ($post['honor_competition_id'] ?? 0));
        $champion = max(0, (int) ($post['honor_champion_deck_id'] ?? 0));
        $runner = max(0, (int) ($post['honor_runner_deck_id'] ?? 0));
        $notes = trim((string) ($post['honor_notes'] ?? ''));
        $owns = !$pdo->inTransaction();
        if ($owns) {
            $pdo->beginTransaction();
        }
        try {
            if ($champion < 1 || $champion === $runner) {
                throw new InvalidArgumentException(
                    'Selecciona un campeon identificado y distinto del subcampeon.',
                );
            }
            if ($eventId > 0) {
                $q = $pdo->prepare(
                    'SELECT * FROM sx_ygo_competitions WHERE id=? AND season_id=? FOR UPDATE',
                );
                $q->execute([$eventId, $seasonId]);
                $event = $q->fetch(PDO::FETCH_ASSOC);
                if (
                    !$event ||
                    !in_array(
                        ygo_normalize_status_key((string) $event['status']),
                        ['completed', 'closed'],
                        true,
                    )
                ) {
                    throw new InvalidArgumentException(
                        'El titulo requiere un evento finalizado confirmado.',
                    );
                }
                if (($event['execution_model'] ?? '') !== 'manual') {
                    throw new InvalidArgumentException(
                        'El campeon de este sistema se determina desde su flujo competitivo; no se sustituye desde Palmares.',
                    );
                }
                if ($notes === '') {
                    throw new InvalidArgumentException(
                        'La adjudicacion manual requiere justificar la evidencia del titulo.',
                    );
                }
                $q = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id=?');
                $q->execute([$eventId]);
                if ($q->fetchColumn()) {
                    throw new InvalidArgumentException(
                        'Esta edicion ya tiene un titulo. Su correccion requiere una revision controlada.',
                    );
                }
                $q = $pdo->prepare(
                    'SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 UNION SELECT deck_a_id FROM sx_ygo_matches WHERE competition_id=? UNION SELECT deck_b_id FROM sx_ygo_matches WHERE competition_id=?',
                );
                $q->execute([$eventId, $eventId, $eventId]);
                $allowed = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
                if (
                    !in_array($champion, $allowed, true) ||
                    ($runner && !in_array($runner, $allowed, true))
                ) {
                    throw new InvalidArgumentException(
                        'El titulo solo puede atribuirse a participantes de esta edicion.',
                    );
                }
                ygo_validate_format_roster(
                    $pdo,
                    (string) $event['event_ruleset'],
                    array_values(array_filter([$champion, $runner])),
                );
            }
            $q = $pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=?');
            $q->execute([$champion]);
            $championTeam = $q->fetchColumn();
            if (!$championTeam) {
                throw new InvalidArgumentException(
                    'El campeon requiere una identidad equipo/deck confirmada.',
                );
            }
            $runnerTeam = null;
            if ($runner) {
                $q->execute([$runner]);
                $runnerTeam = $q->fetchColumn();
                if (!$runnerTeam) {
                    throw new InvalidArgumentException('Subcampeon no identificado.');
                }
            }
            $pdo->prepare(
                'INSERT INTO sx_ygo_honors(season_id,competition_id,champion_deck_id,champion_team_id,runner_up_deck_id,runner_up_team_id,title,notes) VALUES(?,?,?,?,?,?,?,?)',
            )->execute([
                $seasonId,
                $eventId ?: null,
                $champion,
                (int) $championTeam,
                $runner ?: null,
                $runnerTeam ?: null,
                trim((string) ($post['honor_title'] ?? 'Campeon')),
                $notes,
            ]);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )->execute([
                'competition',
                $eventId,
                'manual_honor_confirmed',
                json_encode(
                    [
                        'champion_deck_id' => $champion,
                        'champion_team_id' => (int) $championTeam,
                        'evidence' => $notes,
                    ],
                    JSON_UNESCAPED_UNICODE,
                ),
            ]);
            if ($owns) {
                $pdo->commit();
            }
            return 'palmares';
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return null;
    }
}
