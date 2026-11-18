<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/src/autoload.php';
require_once __DIR__ . '/participant_identity.php';
require_once __DIR__ . '/completion_times.php';

/** Independent players, never two collective sides. No card-content validation. */
function ygo_ffa_begin(array $ids, int $lp = 8000, bool $started = true): array
{
    return \StationX\Yugioh\Domain\Competitions\MultiplayerMatch::begin($ids, $lp, $started);
}
function ygo_ffa_apply(array $s, array $command): array
{
    return \StationX\Yugioh\Domain\Competitions\MultiplayerMatch::apply($s, $command);
}
function ygo_ffa_schema(PDO $p): void
{
    $p->exec(
        'CREATE TABLE IF NOT EXISTS sx_ygo_ffa_matches (id INT AUTO_INCREMENT PRIMARY KEY,competition_id INT NOT NULL,scheduled_on DATE NOT NULL,starts_at TIME NOT NULL,state_json MEDIUMTEXT NOT NULL,revision INT NOT NULL DEFAULT 0,status VARCHAR(24) NOT NULL,winner_deck_id INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_ffa_event(competition_id,scheduled_on)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    );
    $p->exec(
        'CREATE TABLE IF NOT EXISTS sx_ygo_ffa_actions (id BIGINT AUTO_INCREMENT PRIMARY KEY,match_id INT NOT NULL,request_id VARCHAR(80) NOT NULL,payload TEXT NOT NULL,revision INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_ffa_request(match_id,request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    );
}
function ygo_ffa_event(PDO $p, int $id): array
{
    $q = $p->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1 FOR UPDATE');
    $q->execute([$id]);
    $c = $q->fetch(PDO::FETCH_ASSOC);
    if (!$c || $c['execution_model'] !== 'free_for_all') {
        throw new InvalidArgumentException('El evento no es Battle Royale / Free-for-All.');
    }
    ygo_season_require_open($p, (int) $c['season_id']);
    if (
        in_array(strtolower($c['status']), ['completed', 'closed', 'cancelled', 'archived'], true)
    ) {
        throw new InvalidArgumentException('El evento esta cerrado.');
    }
    return $c;
}
function ygo_ffa_create(PDO $p, int $event, array $ids, string $date, string $time): int
{
    return ygo_with_event_write_lock($p, static function () use ($p, $event, $ids, $date, $time) {
        $owns = !$p->inTransaction();
        if ($owns) {
            $p->beginTransaction();
        }
        try {
            $c = ygo_ffa_event($p, $event);
            $state = ygo_ffa_begin($ids, 8000, false);
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (
                !$d ||
                $d->format('Y-m-d') !== $date ||
                !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)
            ) {
                throw new InvalidArgumentException('Fecha u hora invalida.');
            }
            $q = $p->prepare('SELECT starts_on,ends_on FROM sx_ygo_seasons WHERE id=?');
            $q->execute([$c['season_id']]);
            $season = $q->fetch(PDO::FETCH_ASSOC);
            if (
                $date < $season['starts_on'] ||
                $date > $season['ends_on'] ||
                (!empty($c['starts_on']) && $date < $c['starts_on']) ||
                (!empty($c['ends_on']) && $date > $c['ends_on'])
            ) {
                throw new InvalidArgumentException(
                    'Fecha fuera del periodo del evento o temporada.',
                );
            }
            ygo_validate_format_roster($p, $c['event_ruleset'], $ids);
            $identities = ygo_participant_identities($p, $ids, ygo_admission_deck_format($p, $ids));
            foreach ($state['players'] as &$player) {
                $player = array_merge($player, $identities[$player['deck_id']]);
            }
            unset($player);
            $q = $p->prepare(
                'SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1',
            );
            $q->execute([$event]);
            $roster = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            if (array_diff($ids, $roster)) {
                throw new InvalidArgumentException(
                    'Inscribe los decks en Participantes antes de crear el duelo.',
                );
            }
            $guard = ygo_validate_match_schedule($p, 0, $date, $time, null, $ids);
            if (!$guard['ok']) {
                throw new InvalidArgumentException($guard['message']);
            }
            // A player cannot occupy a second table at the same time, including ordinary duels.
            $q = $p->prepare(
                'SELECT state_json,starts_at FROM sx_ygo_ffa_matches WHERE scheduled_on=?',
            );
            $q->execute([$date]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (
                    substr($row['starts_at'], 0, 5) === $time &&
                    array_intersect(
                        $ids,
                        array_column(json_decode($row['state_json'], true)['players'], 'deck_id'),
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Un participante ya tiene otro duelo a esta hora.',
                    );
                }
            }
            foreach ($ids as $id) {
                $q = $p->prepare(
                    'SELECT COUNT(*) FROM sx_ygo_matches WHERE scheduled_on=? AND starts_at=? AND (deck_a_id=? OR deck_b_id=? OR deck_c_id=? OR deck_d_id=? OR deck_e_id=? OR deck_f_id=?)',
                );
                $q->execute([$date, $time, $id, $id, $id, $id, $id, $id]);
                if ($q->fetchColumn()) {
                    throw new InvalidArgumentException(
                        'Un participante ya tiene un combate a esta hora.',
                    );
                }
            }
            $p->prepare(
                'INSERT INTO sx_ygo_ffa_matches(competition_id,scheduled_on,starts_at,state_json,status) VALUES(?,?,?,?,?)',
            )->execute([$event, $date, $time, json_encode($state), $state['status']]);
            $id = (int) $p->lastInsertId();
            $p->prepare(
                "UPDATE sx_ygo_competitions SET status='active',configuration_locked=1 WHERE id=? AND status IN ('pending_configuration','draft','active')",
            )->execute([$event]);
            $p->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )->execute([
                'competition',
                $event,
                'create_ffa',
                json_encode([
                    'ffa_match_id' => $id,
                    'decks' => $ids,
                    'scheduled_on' => $date,
                    'starts_at' => $time,
                ]),
            ]);
            if ($owns) {
                $p->commit();
            }
            return $id;
        } catch (Throwable $e) {
            if ($owns && $p->inTransaction()) {
                $p->rollBack();
            }
            throw $e;
        }
    });
}
function ygo_ffa_command(PDO $p, int $id, int $revision, string $request, array $command): array
{
    return ygo_with_event_write_lock($p, static function () use (
        $p,
        $id,
        $revision,
        $request,
        $command,
    ) {
        if (!preg_match('/^[a-zA-Z0-9_-]{8,80}$/', $request)) {
            throw new InvalidArgumentException('Identificador de accion invalido.');
        }
        $owns = !$p->inTransaction();
        if ($owns) {
            $p->beginTransaction();
        }
        try {
            $q = $p->prepare('SELECT * FROM sx_ygo_ffa_matches WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Duelo inexistente.');
            }
            $state = json_decode($row['state_json'], true);
            $payload = json_encode($command);
            $q = $p->prepare(
                'SELECT payload FROM sx_ygo_ffa_actions WHERE match_id=? AND request_id=?',
            );
            $q->execute([$id, $request]);
            $previous = $q->fetchColumn();
            if ($previous !== false) {
                if ($previous !== $payload) {
                    throw new InvalidArgumentException('La accion repetida contiene otros datos.');
                }
                if ($owns) {
                    $p->commit();
                }
                return $state;
            }
            ygo_ffa_event($p, (int) $row['competition_id']);
            if ((int) $row['revision'] !== $revision) {
                throw new InvalidArgumentException(
                    'Otro usuario actualizo el duelo. Recarga antes de continuar.',
                );
            }
            $today = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format(
                'Y-m-d',
            );
            if ($row['scheduled_on'] > $today) {
                throw new InvalidArgumentException(
                    'No se pueden registrar acciones antes de la fecha del duelo.',
                );
            }
            $state = ygo_ffa_apply($state, $command);
            if ($state['status'] === 'FINALIZADA' && empty($state['completed_at'])) {
                $state['completed_at'] = ygo_completion_now();
            }
            $p->prepare(
                'UPDATE sx_ygo_ffa_matches SET state_json=?,revision=?,status=?,winner_deck_id=? WHERE id=?',
            )->execute([
                json_encode($state),
                $state['revision'],
                $state['status'],
                $state['winner'],
                $id,
            ]);
            $p->prepare(
                'INSERT INTO sx_ygo_ffa_actions(match_id,request_id,payload,revision) VALUES(?,?,?,?)',
            )->execute([$id, $request, $payload, $state['revision']]);
            if ($owns) {
                $p->commit();
            }
            return $state;
        } catch (Throwable $e) {
            if ($owns && $p->inTransaction()) {
                $p->rollBack();
            }
            throw $e;
        }
    });
}
function ygo_ffa_rows(PDO $p): array
{
    $rows = $p
        ->query('SELECT * FROM sx_ygo_ffa_matches ORDER BY scheduled_on,starts_at,id')
        ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['state'] = json_decode($r['state_json'], true);
        unset($r['state_json']);
    }
    unset($r);
    return $rows;
}

function ygo_ffa_finalize(PDO $p, int $event): void
{
    ygo_with_event_write_lock($p, static function () use ($p, $event) {
        $owns = !$p->inTransaction();
        if ($owns) {
            $p->beginTransaction();
        }
        try {
            $c = ygo_ffa_event($p, $event);
            $q = $p->prepare(
                'SELECT status,winner_deck_id FROM sx_ygo_ffa_matches WHERE competition_id=? FOR UPDATE',
            );
            $q->execute([$event]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            $wins = [];
            if (!$rows) {
                throw new InvalidArgumentException('El evento no tiene duelos.');
            }
            foreach ($rows as $r) {
                if ($r['status'] !== 'FINALIZADA' || !$r['winner_deck_id']) {
                    throw new InvalidArgumentException(
                        'Finaliza todas las mesas antes de cerrar el evento.',
                    );
                }
                $id = (int) $r['winner_deck_id'];
                $wins[$id] = ($wins[$id] ?? 0) + 1;
            }
            arsort($wins);
            $top = reset($wins);
            if (count(array_filter($wins, static fn($v) => $v === $top)) !== 1) {
                throw new InvalidArgumentException(
                    'Hay empate en victorias. Juega el desempate antes de finalizar.',
                );
            }
            $champion = (int) array_key_first($wins);
            $q = $p->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=?');
            $q->execute([$champion]);
            $team = $q->fetchColumn();
            $p->prepare(
                "UPDATE sx_ygo_competitions SET status='completed',configuration_locked=1 WHERE id=?",
            )->execute([$event]);
            $p->prepare(
                'INSERT INTO sx_ygo_honors(season_id,competition_id,champion_deck_id,champion_team_id,title) VALUES(?,?,?,?,?)',
            )->execute([$c['season_id'], $event, $champion, $team ?: null, $c['name']]);
            $p->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )->execute([
                'competition',
                $event,
                'finalize_ffa',
                json_encode(['wins' => $wins, 'champion' => $champion]),
            ]);
            if ($owns) {
                $p->commit();
            }
        } catch (Throwable $e) {
            if ($owns && $p->inTransaction()) {
                $p->rollBack();
            }
            throw $e;
        }
    });
}
