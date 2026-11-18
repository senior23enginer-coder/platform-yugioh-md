<?php
declare(strict_types=1);

/**
 * database-seed: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function seed_ygo_database(PDO $pdo, array $divisions, array $fixtures): array
{
    $pdo->prepare("INSERT IGNORE INTO sx_ygo_seasons (name, slug, status) VALUES (?, ?, ?)")
        ->execute(['Temporada 2026', 'temporada-2026', 'draft']);
    $seasonId = (int) $pdo->query("SELECT id FROM sx_ygo_seasons WHERE slug = 'temporada-2026'")->fetchColumn();

    $divisionIds = [];
    if (YGO_AUTO_SEED_DIVISIONS && (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_divisions WHERE season_id = $seasonId")->fetchColumn() === 0) {
        $tier = 1;
        foreach ($divisions as $code => $division) {
            $pdo->prepare("
                INSERT INTO sx_ygo_divisions (season_id, code, name, cup_name, tier_order, deck_count, accent, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$seasonId, $code, $division['name'], $division['cup'], $tier, count($division['decks']), $division['accent'] ?? null, $division['description'] ?? null]);
            $tier++;
        }
    }

    $stmt = $pdo->prepare("SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id");
    $stmt->execute([$seasonId]);
    foreach ($stmt->fetchAll() as $row) {
        $divisionIds[(string) $row['code']] = (int) $row['id'];
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_teams')->fetchColumn() === 0) {
        foreach (['Station X Titans', 'TuluÃ¡ Duel Team', 'Meta Lab', 'Rogue Masters', 'Duel Academy'] as $teamName) {
            $pdo->prepare("INSERT INTO sx_ygo_teams (name, city, active) VALUES (?, 'Tulua', 1)")->execute([$teamName]);
        }
    }
    $teamIds = $pdo->query('SELECT id FROM sx_ygo_teams ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    if (YGO_AUTO_SEED_DIVISIONS && (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_decks')->fetchColumn() === 0) {
        $deckIds = [];
        foreach ($divisions as $code => $division) {
            if (!isset($divisionIds[$code])) {
                continue;
            }
            foreach ($division['decks'] as $index => $deck) {
                $profile = ygo_team_profile_for_deck((string) $deck[1], (string) $deck[1]);
                $teamId = ygo_find_or_create_team($pdo, $profile['name'], $profile);
                $pdo->prepare("
                    INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ")->execute([
                    $divisionIds[$code],
                    $teamId,
                    $deck[0],
                    $deck[1],
                    $deck[1],
                    'Competitivo',
                    $deck[2],
                    'Carta clave 1, carta clave 2, carta clave 3',
                    'Pure, engine mixto, side plan',
                    'Ficha editable desde Gestion.',
                ]);
                $deckStmt = $pdo->prepare("SELECT id FROM sx_ygo_decks WHERE division_id = ? AND name = ?");
                $deckStmt->execute([$divisionIds[$code], $deck[1]]);
                $deckIds[$code][$deck[1]] = (int) $deckStmt->fetchColumn();
            }
        }

        if (YGO_AUTO_SEED_MATCHES) {
            foreach ($fixtures as $code => $rounds) {
                if (!isset($divisionIds[$code], $deckIds[$code])) {
                    continue;
                }
                foreach ($rounds as $round) {
                    foreach ($round['matches'] as $match) {
                        if (!isset($deckIds[$code][$match['a']], $deckIds[$code][$match['b']])) {
                            continue;
                        }
                        $pdo->prepare("
                            INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                            VALUES (?, 'league', ?, ?, ?, ?, 'PENDIENTE')
                            ON DUPLICATE KEY UPDATE deck_a_id = VALUES(deck_a_id), deck_b_id = VALUES(deck_b_id)
                        ")->execute([
                            $divisionIds[$code],
                            $round['ronda'],
                            $match['mesa'],
                            $deckIds[$code][$match['a']],
                            $deckIds[$code][$match['b']],
                        ]);
                    }
                }
            }
        }
    }

    $allDeckIds = $pdo->query('SELECT id FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, id')->fetchAll(PDO::FETCH_COLUMN);
    $firstDivisionId = $divisionIds[array_key_first($divisionIds)] ?? null;
    if (YGO_AUTO_SEED_MATCHES && $firstDivisionId && $allDeckIds && (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_matches WHERE phase = 'qualifier'")->fetchColumn() === 0) {
        $createdPairs = [];
        $table = 1;
        $count = count($allDeckIds);
        foreach ($allDeckIds as $index => $deckA) {
            foreach ([1, 7, 13] as $roundIndex => $offset) {
                $deckB = (int) $allDeckIds[($index + $offset) % $count];
                $deckA = (int) $deckA;
                if ($deckA === $deckB) {
                    continue;
                }
                $pairKey = implode('-', [min($deckA, $deckB), max($deckA, $deckB)]);
                if (isset($createdPairs[$pairKey])) {
                    continue;
                }
                $createdPairs[$pairKey] = true;
                $pdo->prepare("
                    INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                    VALUES (?, 'qualifier', ?, ?, ?, ?, 'PENDIENTE')
                ")->execute([$firstDivisionId, $roundIndex + 1, $table++, $deckA, $deckB]);
            }
        }
    }

    if (YGO_AUTO_SEED_COMPETITIONS && (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_competitions WHERE season_id = $seasonId")->fetchColumn() === 0) {
        $competitions = [
            ['clasificatorio', null, 'Torneo clasificatorio inicial', 'qualifier', 'suizo editable', '+0 days', '+14 days', '#36d8ff'],
            ['liga-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Liga TCG Avanzado', 'league', 'todos contra todos', '+15 days', '+75 days', '#ffd36a'],
            ['liga-ocg', $divisionIds['OCG'] ?? null, 'Liga OCG', 'league', 'todos contra todos', '+15 days', '+75 days', '#9c6cff'],
            ['liga-goat', $divisionIds['GOAT'] ?? null, 'Liga Goat', 'league', 'todos contra todos', '+15 days', '+75 days', '#36d8ff'],
            ['liga-edison', $divisionIds['EDISON'] ?? null, 'Liga Edison', 'league', 'todos contra todos', '+15 days', '+75 days', '#38f29b'],
            ['liga-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Liga Speed Duel', 'league', 'todos contra todos', '+15 days', '+75 days', '#ff7a59'],
            ['liga-genesis', $divisionIds['GENESIS'] ?? null, 'Liga Genesis', 'league', 'todos contra todos', '+15 days', '+75 days', '#6de0c2'],
            ['copa-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Copa TCG Avanzado', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#ffd36a'],
            ['copa-ocg', $divisionIds['OCG'] ?? null, 'Copa OCG', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#9c6cff'],
            ['copa-goat', $divisionIds['GOAT'] ?? null, 'Copa Goat', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#36d8ff'],
            ['copa-edison', $divisionIds['EDISON'] ?? null, 'Copa Edison', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#38f29b'],
            ['copa-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Copa Speed Duel', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#ff7a59'],
            ['copa-genesis', $divisionIds['GENESIS'] ?? null, 'Copa Genesis', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#6de0c2'],
            ['world-duel', null, 'World Duel Championship', 'world', '6 grupos + eliminacion', '+95 days', '+120 days', '#ff5c7a'],
            ['copa-eternal', null, 'Copa Eternal', 'final', 'top 2 por categoria', '+121 days', '+135 days', '#ffffff'],
        ];
        foreach ($competitions as $competition) {
            $startDays = (int) str_replace(['+', ' days'], '', $competition[5]);
            $endDays = (int) str_replace(['+', ' days'], '', $competition[6]);
            $startsOn = date('Y-m-d', strtotime("+$startDays days"));
            $endsOn = date('Y-m-d', strtotime("+$endDays days"));
            $pdo->prepare("
                INSERT INTO sx_ygo_competitions (season_id, division_id, code, name, type, format, status, starts_on, ends_on, accent, active)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, 1)
            ")->execute([
                $seasonId,
                $competition[1],
                $competition[0],
                $competition[2],
                $competition[3],
                $competition[4],
                $startsOn,
                $endsOn,
                $competition[7],
            ]);
        }
    }
    if (YGO_AUTO_SEED_COMPETITIONS) {
        try {
            ygo_ensure_default_events($pdo, $seasonId, $divisionIds);
            ygo_ensure_default_event_dependencies($pdo, $seasonId);
        } catch (Throwable $error) {
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['event_seed', $seasonId, 'blocked_default_event_seed', json_encode(['message' => $error->getMessage()], JSON_UNESCAPED_UNICODE)]);
        }
    }
    ygo_sync_format_categories($pdo, $seasonId);

    return [
        'seasons' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_seasons')->fetchColumn(),
        'divisions' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_divisions')->fetchColumn(),
        'decks' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_decks')->fetchColumn(),
        'matches' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_matches')->fetchColumn(),
        'teams' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_teams')->fetchColumn(),
        'competitions' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_competitions')->fetchColumn(),
    ];
}

function ygo_remove_seeded_divisions(PDO $pdo, int $seasonId, array $seedDivisionDefinitions): int
{
    return 0;
    $marker = 'cleanup_seeded_divisions_20260910_b';
    $check = $pdo->prepare('SELECT setting_value FROM sx_ygo_settings WHERE season_id = ? AND setting_key = ? LIMIT 1');
    $check->execute([$seasonId, $marker]);
    if ((string) $check->fetchColumn() === '1') {
        return 0;
    }
    $seedCodes = array_keys($seedDivisionDefinitions);
    if (!$seedCodes) {
        ygo_save_setting($pdo, $marker, '1');
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($seedCodes), '?'));
    $select = $pdo->prepare("SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND code IN ($placeholders)");
    $select->execute([$seasonId, ...$seedCodes]);
    $ids = array_values(array_filter(array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN)), static fn(int $id): bool => $id > 0));
    if (!$ids) {
        ygo_save_setting($pdo, $marker, '1');
        return 0;
    }
    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE sx_ygo_decks SET division_id = NULL WHERE division_id IN ($idPlaceholders)")->execute($ids);
    $pdo->prepare("UPDATE sx_ygo_competitions SET division_id = NULL, promotion_to_division_id = NULL, relegation_to_division_id = NULL WHERE division_id IN ($idPlaceholders) OR promotion_to_division_id IN ($idPlaceholders) OR relegation_to_division_id IN ($idPlaceholders)")
        ->execute([...$ids, ...$ids, ...$ids]);
    $delete = $pdo->prepare("DELETE FROM sx_ygo_divisions WHERE id IN ($idPlaceholders)");
    $delete->execute($ids);
    $deleted = $delete->rowCount();
    ygo_save_setting($pdo, $marker, '1');
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['division', $seasonId, 'delete_seeded_divisions', json_encode(['codes' => $seedCodes, 'ids' => $ids, 'deleted' => $deleted], JSON_UNESCAPED_UNICODE)]);
    return $deleted;
}

function ygo_ensure_default_events(PDO $pdo, int $seasonId, array $divisionIds): void
{
    $eventSeeds = [
        ['clasificatorio-general', null, 'Clasificatorio General', 'qualifier', 'tcg_advanced', '#36d8ff', 0, 14],
        ['clasificatorio-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Clasificatorio TCG Avanzado', 'qualifier', 'tcg_advanced', '#ffd36a', 0, 14],
        ['liga-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Liga TCG Avanzado', 'league', 'tcg_advanced', '#ffd36a', 15, 75],
        ['copa-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Copa TCG Avanzado', 'cup', 'tcg_advanced', '#ffd36a', 30, 90],
        ['clasificatorio-ocg', $divisionIds['OCG'] ?? null, 'Clasificatorio OCG', 'qualifier', 'ocg', '#9c6cff', 0, 14],
        ['torneo-ocg', $divisionIds['OCG'] ?? null, 'Torneo OCG', 'special', 'ocg', '#9c6cff', 15, 75],
        ['liga-ocg', $divisionIds['OCG'] ?? null, 'Liga OCG', 'league', 'ocg', '#9c6cff', 15, 75],
        ['copa-ocg', $divisionIds['OCG'] ?? null, 'Copa OCG', 'cup', 'ocg', '#9c6cff', 30, 90],
        ['torneo-goat', $divisionIds['GOAT'] ?? null, 'Torneo Goat', 'special', 'goat', '#36d8ff', 15, 75],
        ['liga-goat', $divisionIds['GOAT'] ?? null, 'Liga Goat', 'league', 'goat', '#36d8ff', 15, 75],
        ['copa-goat', $divisionIds['GOAT'] ?? null, 'Copa Goat', 'cup', 'goat', '#36d8ff', 30, 90],
        ['clasificatorio-edison', $divisionIds['EDISON'] ?? null, 'Clasificatorio Edison', 'qualifier', 'edison', '#38f29b', 0, 14],
        ['torneo-edison', $divisionIds['EDISON'] ?? null, 'Torneo Edison', 'special', 'edison', '#38f29b', 15, 75],
        ['liga-edison', $divisionIds['EDISON'] ?? null, 'Liga Edison', 'league', 'edison', '#38f29b', 15, 75],
        ['copa-edison', $divisionIds['EDISON'] ?? null, 'Copa Edison', 'cup', 'edison', '#38f29b', 30, 90],
        ['clasificatorio-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Clasificatorio Speed Duel', 'qualifier', 'speed_duel', '#ff7a59', 0, 14],
        ['torneo-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Torneo Speed Duel', 'special', 'speed_duel', '#ff7a59', 15, 75],
        ['liga-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Liga Speed Duel', 'league', 'speed_duel', '#ff7a59', 15, 75],
        ['copa-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Copa Speed Duel', 'cup', 'speed_duel', '#ff7a59', 30, 90],
        ['clasificatorio-genesis', $divisionIds['GENESIS'] ?? null, 'Clasificatorio Genesis', 'qualifier', 'genesis', '#6de0c2', 0, 14],
        ['torneo-genesis', $divisionIds['GENESIS'] ?? null, 'Torneo Genesis', 'special', 'genesis', '#6de0c2', 15, 75],
        ['liga-genesis', $divisionIds['GENESIS'] ?? null, 'Liga Genesis', 'league', 'genesis', '#6de0c2', 15, 75],
        ['copa-genesis', $divisionIds['GENESIS'] ?? null, 'Copa Genesis', 'cup', 'genesis', '#6de0c2', 30, 90],
        ['world-duel', null, 'World Duel Championship', 'world', 'tcg_advanced', '#ff5c7a', 95, 120],
        ['copa-eternal', null, 'Copa Eternal', 'final', 'tcg_advanced', '#ffffff', 121, 135],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_competitions
            (season_id, division_id, code, name, type, scope, format, match_format, best_of, participants_per_side, schedule_strategy, event_ruleset, deck_pool, victory_condition, defeat_condition, draw_condition, status, starts_on, ends_on, accent, rules, active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            division_id=VALUES(division_id),
            type=VALUES(type),
            scope=VALUES(scope),
            match_format=VALUES(match_format),
            best_of=VALUES(best_of),
            participants_per_side=VALUES(participants_per_side),
            schedule_strategy=VALUES(schedule_strategy),
            event_ruleset=VALUES(event_ruleset),
            deck_pool=VALUES(deck_pool),
            victory_condition=VALUES(victory_condition),
            defeat_condition=VALUES(defeat_condition),
            draw_condition=VALUES(draw_condition),
            active=1
    ");
    $archiveDuplicate = $pdo->prepare("
        UPDATE sx_ygo_competitions
        SET active = 0, status = 'archived'
        WHERE season_id = ?
          AND code <> ?
          AND name = ?
          AND type = ?
          AND COALESCE(division_id, 0) = COALESCE(?, 0)
          AND event_ruleset <> ?
    ");
    $existingSeed = $pdo->prepare('
        SELECT id
        FROM sx_ygo_competitions
        WHERE season_id = ? AND name = ? AND type = ? AND COALESCE(division_id, 0) = COALESCE(?, 0) AND active = 1
        ORDER BY id
        LIMIT 1
    ');
    $updateSeed = $pdo->prepare('
        UPDATE sx_ygo_competitions
        SET division_id=?, type=?, scope=?, match_format=?, best_of=?, participants_per_side=?, schedule_strategy=?, event_ruleset=?, deck_pool=?, victory_condition=?, defeat_condition=?, draw_condition=?, accent=?, active=1
        WHERE id=?
    ');
    foreach ($eventSeeds as $seed) {
        [$code, $divisionId, $name, $type, $ruleset, $accent, $startOffset, $endOffset] = $seed;
        $defaults = ygo_competition_defaults($type, $ruleset, $name);
        $scope = $divisionId === null ? 'global' : 'division';
        $existingSeed->execute([$seasonId, $name, $type, $divisionId]);
        $existingSeedId = (int) ($existingSeed->fetchColumn() ?: 0);
        if ($existingSeedId > 0) {
            $updateSeed->execute([
                $divisionId,
                $type,
                $scope,
                $defaults['match_format'],
                $defaults['best_of'],
                $defaults['participants_per_side'],
                $defaults['schedule_strategy'],
                $ruleset,
                $defaults['deck_pool'],
                $defaults['victory_condition'],
                $defaults['defeat_condition'],
                $defaults['draw_condition'],
                $accent,
                $existingSeedId,
            ]);
            $archiveDuplicate->execute([$seasonId, $code, $name, $type, $divisionId, $ruleset]);
            continue;
        }
        $code = ygo_generate_competition_code($pdo, $seasonId, $type, $ruleset, $name);
        $stmt->execute([
            $seasonId,
            $divisionId,
            $code,
            $name,
            $type,
            $scope,
            $defaults['format'],
            $defaults['match_format'],
            $defaults['best_of'],
            $defaults['participants_per_side'],
            $defaults['schedule_strategy'],
            $ruleset,
            $defaults['deck_pool'],
            $defaults['victory_condition'],
            $defaults['defeat_condition'],
            $defaults['draw_condition'],
            date('Y-m-d', strtotime('+' . (int) $startOffset . ' days')),
            date('Y-m-d', strtotime('+' . (int) $endOffset . ' days')),
            $accent,
            $defaults['rules'],
        ]);
        $archiveDuplicate->execute([$seasonId, $code, $name, $type, $divisionId, $ruleset]);
    }
}

function ygo_ensure_default_event_dependencies(PDO $pdo, int $seasonId): void
{
    $stmt = $pdo->prepare("
        SELECT id, type, event_ruleset, code
        FROM sx_ygo_competitions
        WHERE season_id = ? AND active = 1
        ORDER BY id
    ");
    $stmt->execute([$seasonId]);
    $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $qualifierByRuleset = [];
    foreach ($competitions as $competition) {
        if ((string) $competition['type'] === 'qualifier') {
            $ruleset = (string) (($competition['event_ruleset'] ?? '') ?: 'tcg_advanced');
            if (!isset($qualifierByRuleset[$ruleset]) || str_contains((string) $competition['code'], $ruleset)) {
                $qualifierByRuleset[$ruleset] = (int) $competition['id'];
            }
        }
    }
    $insert = $pdo->prepare("
        INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
        VALUES (?, ?, ?, 'completed_with_results', 0, 1)
        ON DUPLICATE KEY UPDATE active=1, condition_type=VALUES(condition_type), take_count=VALUES(take_count)
    ");
    foreach ($competitions as $competition) {
        $type = (string) $competition['type'];
        if (!in_array($type, ['league', 'cup', 'special'], true)) {
            continue;
        }
        $ruleset = (string) (($competition['event_ruleset'] ?? '') ?: 'tcg_advanced');
        $requiredId = $qualifierByRuleset[$ruleset] ?? null;
        if ($requiredId && $requiredId !== (int) $competition['id']) {
            $insert->execute([$seasonId, (int) $competition['id'], $requiredId]);
        }
    }
}

function ygo_ensure_default_rulesets(PDO $pdo, int $seasonId, array $rulesetOptions): void
{
    $currentBanlists = [
        'master_duel' => ['Master Duel Forbidden/Limited List', 'Septiembre 2026', '2026-09-03', 'Lista actual de Master Duel verificada como actualizada el 2026-09-03. Cargar detalle carta por carta desde Reglamento si se requiere control por carta.'],
        'tcg_advanced' => ['TCG Advanced Forbidden & Limited List', 'Mayo 2026', '2026-05-18', 'Lista TCG Advanced vigente publicada el 2026-05-18.'],
        'ocg' => ['OCG Forbidden & Limited List', 'Julio 2026', '2026-07-01', 'Lista OCG vigente desde 2026-07-01.'],
        'goat' => ['Goat Format Forbidden & Limited List', 'Abril 2005', '2005-04-01', 'Formato historico Goat basado en la lista abril 2005.'],
        'edison' => ['Edison Format Forbidden & Limited List', 'Marzo 2010', '2010-03-01', 'Formato historico Edison basado en la lista TCG marzo 2010.'],
        'speed_duel' => ['Speed Duel Forbidden/Limited List', 'Actual por definir', null, 'Speed Duel usa lista propia por grupos Limited 1/2/3; registrar version exacta antes de jugar.'],
        'genesis' => ['Genesys Point List', 'Agosto 2026', '2026-08-01', 'Genesys no usa banlist tradicional; usa sistema de puntos/cupo. Registrar point list exacta antes de jugar.'],
        'custom' => ['Reglamento personalizado', 'Por definir', null, 'Reglamento local editable.'],
    ];
    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_rulesets
            (season_id, code, label, format_family, banlist_name, banlist_version, banlist_effective_on, match_format, best_of, participants_per_side, schedule_strategy, deck_pool, victory_condition, defeat_condition, draw_condition, notes, active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, '1v1', 3, 1, 'manual', ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE label=VALUES(label), format_family=VALUES(format_family), banlist_name=VALUES(banlist_name), banlist_version=VALUES(banlist_version), banlist_effective_on=VALUES(banlist_effective_on), active=1
    ");
    foreach ($rulesetOptions as $code => $label) {
        $defaults = ygo_competition_defaults($code === 'custom' ? 'special' : 'league', (string) $code, (string) $label);
        $banlist = $currentBanlists[(string) $code] ?? [(string) $label, 'Por definir', null, 'Version pendiente de registrar.'];
        $stmt->execute([
            $seasonId,
            (string) $code,
            (string) $label,
            (string) $code,
            $banlist[0],
            $banlist[1],
            $banlist[2],
            (string) ($defaults['deck_pool'] ?? 'all_active'),
            (string) ($defaults['victory_condition'] ?? 'Resultado definido por reglamento'),
            (string) ($defaults['defeat_condition'] ?? 'Resultado definido por reglamento'),
            (string) ($defaults['draw_condition'] ?? 'Empate definido por reglamento'),
            $banlist[3],
        ]);
    }
}

function ygo_ensure_default_ruleset_versions(PDO $pdo, int $seasonId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_ruleset_versions
            (season_id, code, category, system, version_label, date_label, effective_on, sort_order, active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            category=VALUES(category),
            system=VALUES(system),
            version_label=VALUES(version_label),
            date_label=VALUES(date_label),
            effective_on=VALUES(effective_on),
            sort_order=VALUES(sort_order),
            active=1
    ");
    foreach (ygo_ruleset_version_catalog() as $index => $item) {
        $effectiveOn = $item['sort_date'] === '9999-12-31' ? null : $item['sort_date'];
        $stmt->execute([
            $seasonId,
            (string) $item['value'],
            (string) $item['category'],
            (string) $item['system'],
            (string) $item['version'],
            (string) $item['date_label'],
            $effectiveOn,
            $index + 1,
        ]);
    }
}

function ygo_ensure_default_game_rules(PDO $pdo, int $seasonId): void
{
    unset($pdo, $seasonId);
    // Las reglas y sus condiciones se administran exclusivamente en sx_ygo_game_rules
    // y sx_ygo_game_rule_conditions desde el CRUD. No se siembran ni se corrigen desde codigo.
}

function ygo_ensure_default_match_types(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_match_types (code, label, participants_per_side, sort_order, active)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE label=VALUES(label), participants_per_side=VALUES(participants_per_side), sort_order=VALUES(sort_order), active=1
    ");
    foreach ([['1v1', '1v1', 1, 1], ['2v2', '2v2', 2, 2], ['3v3', '3v3', 3, 3]] as $row) {
        $stmt->execute($row);
    }
}
