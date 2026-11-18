<?php
declare(strict_types=1);

/**
 * deck-eligibility: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_deck_delete_blocker(PDO $pdo, int $deckId): ?array
{
    if ($deckId <= 0) {
        return null;
    }
    $blockedStatuses = [
        'pending_configuration',
        'pendiente_configuracion',
        'pending_event',
        'pendiente_evento',
        'active',
        'activo',
        'completed',
        'finalizado',
        'finalizada',
        'cancelled',
        'canceled',
        'anulado',
        'anulada',
    ];
    $placeholders = implode(',', array_fill(0, count($blockedStatuses), '?'));
    $competitionStmt = $pdo->prepare("
        SELECT c.id, c.name, c.status, 'seleccionado en evento' AS source
        FROM sx_ygo_competition_decks cd
        JOIN sx_ygo_competitions c ON c.id = cd.competition_id
        WHERE cd.deck_id = ? AND cd.active = 1 AND c.active = 1
          AND LOWER(REPLACE(c.status, ' ', '_')) IN ($placeholders)
        ORDER BY FIELD(LOWER(REPLACE(c.status, ' ', '_')), 'active', 'activo', 'pending_event', 'pendiente_evento', 'pending_configuration', 'pendiente_configuracion', 'completed', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'), c.id DESC
        LIMIT 1
    ");
    $competitionStmt->execute(array_merge([$deckId], $blockedStatuses));
    $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC);
    if ($competition) {
        return $competition;
    }

    $matchStmt = $pdo->prepare("
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

function ygo_allowed_deck_ids_for_competition(PDO $pdo, ?int $competitionId, int $manualDivisionId): array
{
    if (!$competitionId) {
        if ($manualDivisionId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL AND division_id = ?');
            $stmt->execute([$manualDivisionId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        $stmt = $pdo->query('SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL');
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition) {
        return [];
    }

    if(ygo_admission_required($competition) || ygo_admission_load($pdo,$competition)!==null){$admission=ygo_admission_view($pdo,$competition);return $admission['ok']?($admission['selection']['ids']??[]):[];}
    $pool = (string) (($competition['deck_pool'] ?? '') ?: 'all_active');
    $formatIds = ygo_format_deck_ids($pdo, (string)$competition['event_ruleset']);
    $formatFilter = static fn(array $ids): array => array_values(array_intersect(array_map('intval',$ids),$formatIds));
    $participantSource = strtoupper((string) (($competition['participant_source'] ?? '') ?: 'MANUAL'));
    $accessType = strtoupper((string) (($competition['access_type'] ?? '') ?: 'OPEN'));
    $manualStmt = $pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1 ORDER BY sort_order, id');
    $manualStmt->execute([$competitionId]);
    $manualDeckIds = array_map('intval', $manualStmt->fetchAll(PDO::FETCH_COLUMN));
    $deckPolicy = strtoupper((string) (($competition['deck_policy'] ?? '') ?: 'OPTIONAL_REGISTRATION'));
    if (in_array($deckPolicy, ['REQUIRED_REGISTRATION', 'DECK_LOCK_EVENT', 'DECK_LOCK_PHASE', 'DECK_LOCK_ROUND'], true)) {
        return $formatFilter($manualDeckIds);
    }
    if ($manualDeckIds && in_array($participantSource, ['MANUAL', 'CUSTOM'], true)) {
        return $formatFilter($manualDeckIds);
    }
    if ($manualDeckIds && !in_array($pool, ['division', 'all_active'], true)) {
        return $formatFilter($manualDeckIds);
    }

    $divisionId = $competition['division_id'] !== null ? (int) $competition['division_id'] : $manualDivisionId;
    if (!$divisionId && !empty($competition['event_ruleset'])) {
        $divisionCode = ygo_ruleset_division_code((string) $competition['event_ruleset']);
        if ($divisionCode) {
            $divisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE code = ? AND active = 1 ORDER BY id DESC LIMIT 1');
            $divisionStmt->execute([$divisionCode]);
            $divisionId = (int) $divisionStmt->fetchColumn();
        }
    }

    if ($pool === 'division') return $formatIds;

    if (in_array($pool, ['qualified', 'top_cut'], true) || in_array($participantSource, ['PREVIOUS_EVENT', 'QUALIFIER', 'RANKING'], true) || in_array($accessType, ['INVITATIONAL', 'QUALIFIER', 'LAST_CHANCE_QUALIFIER'], true)) {
        $dependencyStmt = $pdo->prepare("
            SELECT depends_on_competition_id, condition_type, take_count
            FROM sx_ygo_competition_dependencies
            WHERE competition_id = ? AND active = 1
            ORDER BY id
        ");
        $dependencyStmt->execute([$competitionId]);
        $dependencyRows = $dependencyStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($dependencyRows) {
            $qualifiedIds = [];
            foreach ($dependencyRows as $dependencyRow) {
                $dependencyType = (string) ($dependencyRow['condition_type'] ?? 'completed_with_results');
                $requiredId = (int) ($dependencyRow['depends_on_competition_id'] ?? 0);
                if ($requiredId <= 0) {
                    continue;
                }
                if (in_array($dependencyType, ['top_n', 'winner_to_final', 'defending_champion'], true)) {
                    $take = $dependencyType === 'top_n' ? max(1, (int) ($dependencyRow['take_count'] ?? 0) ?: 4) : 1;
                    foreach (array_slice(ygo_competition_standings($pdo, $requiredId), 0, $take) as $standingRow) {
                        $deckId = (int) ($standingRow['deck_id'] ?? 0);
                        if ($deckId > 0) {
                            $qualifiedIds[] = $deckId;
                        }
                    }
                }
            }
            if ($qualifiedIds) {
                return $formatFilter(array_values(array_unique($qualifiedIds)));
            }
            $requiredIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['depends_on_competition_id'], $dependencyRows)));
            $placeholders = implode(',', array_fill(0, count($requiredIds), '?'));
            $qualifiedStmt = $pdo->prepare("
                SELECT DISTINCT deck_id FROM (
                    SELECT m.deck_a_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_b_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_c_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_c_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_d_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_d_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_e_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_e_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_f_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_f_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                ) qualified
            ");
            $qualifiedStmt->execute(array_merge($requiredIds, $requiredIds, $requiredIds, $requiredIds, $requiredIds, $requiredIds));
            $qualifiedIds = array_map('intval', $qualifiedStmt->fetchAll(PDO::FETCH_COLUMN));
            if ($qualifiedIds) {
                return $formatFilter($pool === 'top_cut' ? array_slice($qualifiedIds, 0, 24) : $qualifiedIds);
            }
        }
        if ($divisionId > 0) {
            $deckStmt = $pdo->prepare('SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL AND division_id = ?');
            $deckStmt->execute([$divisionId]);
            return array_map('intval', $deckStmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    return $formatIds;
}

function ygo_match_deck_validation(PDO $pdo, ?int $competitionId, int $manualDivisionId, array $deckIds, int $participantsPerSide = 1): array
{
    $allowed = ygo_allowed_deck_ids_for_competition($pdo, $competitionId, $manualDivisionId);
    if (!$allowed) {
        return ['ok' => false, 'message' => 'No hay decks permitidos para el formato del evento.'];
    }
    $allowedSet = array_fill_keys(array_map('strval', $allowed), true);
    $cleanDeckIds = [];
    foreach ($deckIds as $deckId) {
        if ($deckId !== null && $deckId > 0 && !isset($allowedSet[(string) $deckId])) {
            return ['ok' => false, 'message' => 'Hay decks que no pertenecen al formato/pool del evento.'];
        }
        if ($deckId !== null && $deckId > 0) {
            $cleanDeckIds[] = (int) $deckId;
        }
    }
    if (count($cleanDeckIds) !== count(array_unique($cleanDeckIds))) {
        return ['ok' => false, 'message' => 'No se puede repetir un deck en el mismo match.'];
    }
    if (!$cleanDeckIds) {
        return ['ok' => false, 'message' => 'Debes seleccionar decks para crear el match.'];
    }
    $placeholders = implode(',', array_fill(0, count($cleanDeckIds), '?'));
    $stmt = $pdo->prepare("SELECT id, division_id, team_id FROM sx_ygo_decks WHERE id IN ($placeholders) AND active = 1");
    $stmt->execute($cleanDeckIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($cleanDeckIds)) {
        return ['ok' => false, 'message' => 'Uno o mas decks no existen o estan inactivos.'];
    }
    $byId = [];
    foreach ($rows as $row) {
        if (empty($row['team_id'])) {
            return ['ok' => false, 'message' => 'Todo deck debe pertenecer a un equipo.'];
        }
        $byId[(int) $row['id']] = $row;
    }
    $divisionIds = array_unique(array_map(static fn(array $row): int => (int) $row['division_id'], $rows));
    if (count($divisionIds) > 1 || ($manualDivisionId > 0 && (int) $divisionIds[0] !== $manualDivisionId)) {
        return ['ok' => false, 'message' => 'Todos los decks del match deben pertenecer al mismo formato del evento.'];
    }
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $sideA = array_values(array_filter([$deckIds[0] ?? null, $deckIds[2] ?? null, $deckIds[4] ?? null], static fn($id): bool => $id !== null && (int) $id > 0));
    $sideB = array_values(array_filter([$deckIds[1] ?? null, $deckIds[3] ?? null, $deckIds[5] ?? null], static fn($id): bool => $id !== null && (int) $id > 0));
    if (count($sideA) !== $participantsPerSide || count($sideB) !== $participantsPerSide) {
        return ['ok' => false, 'message' => "El match {$participantsPerSide}v{$participantsPerSide} requiere {$participantsPerSide} decks por lado."];
    }
    if ($participantsPerSide > 1) {
        $teamA = (int) $byId[(int) $sideA[0]]['team_id'];
        $teamB = (int) $byId[(int) $sideB[0]]['team_id'];
        foreach ($sideA as $deckId) {
            if ((int) $byId[(int) $deckId]['team_id'] !== $teamA) {
                return ['ok' => false, 'message' => 'En 2v2/3v3 todos los decks del lado A deben ser del mismo equipo.'];
            }
        }
        foreach ($sideB as $deckId) {
            if ((int) $byId[(int) $deckId]['team_id'] !== $teamB) {
                return ['ok' => false, 'message' => 'En 2v2/3v3 todos los decks del lado B deben ser del mismo equipo.'];
            }
        }
        if ($teamA === $teamB) {
            return ['ok' => false, 'message' => 'En 2v2/3v3 los dos lados deben ser equipos distintos.'];
        }
    }
    return ['ok' => true, 'message' => 'OK'];
}

function ygo_match_decks_allowed(PDO $pdo, ?int $competitionId, int $manualDivisionId, array $deckIds, int $participantsPerSide = 1): bool
{
    return ygo_match_deck_validation($pdo, $competitionId, $manualDivisionId, $deckIds, $participantsPerSide)['ok'];
}

function ygo_roster_groups_for_match(PDO $pdo, array $selectedDeckIds, int $participantsPerSide): array
{
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
    if (!$selectedDeckIds) {
        return [];
    }
    if ($participantsPerSide === 1) {
        return array_map(static fn(int $id): array => [$id], $selectedDeckIds);
    }
    $placeholders = implode(',', array_fill(0, count($selectedDeckIds), '?'));
    $stmt = $pdo->prepare("SELECT id, team_id FROM sx_ygo_decks WHERE id IN ($placeholders) AND active = 1 AND team_id IS NOT NULL");
    $stmt->execute($selectedDeckIds);
    $teamByDeck = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teamByDeck[(int) $row['id']] = (int) $row['team_id'];
    }
    $teams = [];
    foreach ($selectedDeckIds as $deckId) {
        $teamId = $teamByDeck[$deckId] ?? 0;
        if ($teamId <= 0) {
            continue;
        }
        $teams[$teamId] = $teams[$teamId] ?? [];
        $teams[$teamId][] = $deckId;
    }
    $groups = [];
    foreach ($teams as $teamDecks) {
        if (count($teamDecks) >= $participantsPerSide) {
            $groups[] = array_slice($teamDecks, 0, $participantsPerSide);
        }
    }
    return $groups;
}
