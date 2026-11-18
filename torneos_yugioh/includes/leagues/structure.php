<?php
declare(strict_types=1);

/**
 * league-structure: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_sync_format_categories(PDO $pdo, int $seasonId): void
{
    global $divisionDefinitions;

    $managed = $pdo->prepare("SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key='league_structure_managed' LIMIT 1");
    $managed->execute([$seasonId]);
    if ((string) $managed->fetchColumn() === '1') {
        return;
    }

    $legacyCodes = [
        'TITAN' => 'TCG_AVANZADO',
        'LEGEND' => 'OCG',
        'ELITE' => 'GOAT',
        'CHALLENGER' => 'EDISON',
    ];
    foreach ($legacyCodes as $legacyCode => $newCode) {
        if (!isset($divisionDefinitions[$newCode])) {
            continue;
        }
        $definition = $divisionDefinitions[$newCode];
        $pdo->prepare('
            UPDATE sx_ygo_divisions
            SET code=?, name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?
            WHERE season_id=? AND code=?
        ')->execute([
            $newCode,
            $definition['name'],
            $definition['cup'],
            array_search($newCode, array_keys($divisionDefinitions), true) + 1,
            $definition['size'],
            $definition['accent'],
            $definition['description'],
            $seasonId,
            $legacyCode,
        ]);
    }

    $existingStmt = $pdo->prepare('SELECT code FROM sx_ygo_divisions WHERE season_id = ?');
    $existingStmt->execute([$seasonId]);
    $existingCodes = array_map('strval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$existingCodes) {
        return;
    }
    foreach ($divisionDefinitions as $code => $definition) {
        $tier = array_search($code, array_keys($divisionDefinitions), true) + 1;
        if (!in_array($code, $existingCodes, true)) {
            continue;
        }
        $pdo->prepare('
            UPDATE sx_ygo_divisions
            SET name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?
            WHERE season_id=? AND code=?
        ')->execute([
            $definition['name'],
            $definition['cup'],
            $tier,
            $definition['size'],
            $definition['accent'],
            $definition['description'],
            $seasonId,
            $code,
        ]);
    }

}

function ygo_ensure_goat_league_structure(PDO $pdo, int $seasonId): array
{
    $legacy = $pdo->query("SELECT id FROM sx_ygo_seasons WHERE slug='temporada-2026'")->fetchColumn();
    if ($seasonId !== (int) $legacy) return ['division_id'=>0,'league_id'=>0,'disabled_qualifiers'=>0];
    $managed = $pdo->prepare("SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key='league_structure_managed' LIMIT 1");
    $managed->execute([$seasonId]);
    if ((string) $managed->fetchColumn() === '1') {
        $division = $pdo->prepare("SELECT id, league_competition_id FROM sx_ygo_divisions WHERE season_id=? AND code='GOAT' AND active=1 LIMIT 1");
        $division->execute([$seasonId]);
        $row = $division->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['division_id' => (int) ($row['id'] ?? 0), 'league_id' => (int) ($row['league_competition_id'] ?? 0), 'disabled_qualifiers' => 0];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('
            INSERT INTO sx_ygo_divisions
                (season_id, code, name, cup_name, tier_order, deck_count, accent, description, active)
            VALUES
                (?, "GOAT", "Primera Division GOAT", "Copa GOAT", 3, 0, "#36d8ff", "Primera division de la Liga GOAT para partidas retro y control de recursos.", 1)
            ON DUPLICATE KEY UPDATE
                name=VALUES(name), cup_name=VALUES(cup_name), accent=VALUES(accent), description=VALUES(description), active=1
        ')->execute([$seasonId]);

        $divisionStmt = $pdo->prepare('SELECT id, league_competition_id FROM sx_ygo_divisions WHERE season_id=? AND code="GOAT" AND active=1 LIMIT 1');
        $divisionStmt->execute([$seasonId]);
        $goatDivision = $divisionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $divisionId = (int) ($goatDivision['id'] ?? 0);
        $linkedLeagueId = (int) ($goatDivision['league_competition_id'] ?? 0);

        $leagueStmt = $pdo->prepare('
            SELECT id
            FROM sx_ygo_competitions
            WHERE season_id=? AND active=1 AND type="league"
              AND (LOWER(COALESCE(event_ruleset, ""))="goat" OR LOWER(name) LIKE "%goat%")
            ORDER BY (code="liga-goat") DESC, id DESC
            LIMIT 1
        ');
        $leagueStmt->execute([$seasonId]);
        $leagueId = (int) ($leagueStmt->fetchColumn() ?: 0);

        $linked = 0;
        if ($divisionId > 0 && $leagueId > 0 && $linkedLeagueId <= 0) {
            $leagueUpdate = $pdo->prepare('UPDATE sx_ygo_competitions SET division_id=?, event_ruleset="goat", deck_pool="division" WHERE id=? AND season_id=?');
            $leagueUpdate->execute([$divisionId, $leagueId, $seasonId]);
            $divisionUpdate = $pdo->prepare('UPDATE sx_ygo_divisions SET league_competition_id=? WHERE id=? AND season_id=?');
            $divisionUpdate->execute([$leagueId, $divisionId, $seasonId]);
            $linked = max($leagueUpdate->rowCount(), $divisionUpdate->rowCount());
        }

        $qualifierStmt = $pdo->prepare('
            SELECT id
            FROM sx_ygo_competitions
            WHERE season_id=? AND active=1 AND type="qualifier"
              AND (LOWER(COALESCE(event_ruleset, ""))="goat" OR LOWER(name) LIKE "%goat%")
        ');
        $qualifierStmt->execute([$seasonId]);
        $qualifierIds = array_values(array_filter(array_map('intval', $qualifierStmt->fetchAll(PDO::FETCH_COLUMN)), static fn(int $id): bool => $id > 0));
        if ($qualifierIds) {
            $placeholders = implode(',', array_fill(0, count($qualifierIds), '?'));
            $pdo->prepare("UPDATE sx_ygo_competitions SET active=0 WHERE id IN ($placeholders)")->execute($qualifierIds);
            $pdo->prepare("UPDATE sx_ygo_events SET active=0 WHERE competition_id IN ($placeholders)")->execute($qualifierIds);
            $pdo->prepare("UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id IN ($placeholders) OR depends_on_competition_id IN ($placeholders)")
                ->execute([...$qualifierIds, ...$qualifierIds]);
        }

        if ($linked > 0 || $qualifierIds) {
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition', $leagueId ?: null, 'ensure_goat_league_structure', json_encode([
                    'division_id' => $divisionId,
                    'league_id' => $leagueId,
                    'disabled_qualifier_ids' => $qualifierIds,
                ], JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->commit();
        return ['division_id' => $divisionId, 'league_id' => $leagueId, 'disabled_qualifiers' => count($qualifierIds)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function ygo_provisional_category_for_rank(int $rank): string
{
    global $divisionDefinitions;

    $cursor = 0;
    foreach ($divisionDefinitions as $definition) {
        $cursor += (int) ($definition['size'] ?? 0);
        if ($rank <= $cursor) {
            return (string) $definition['short'];
        }
    }
    $last = end($divisionDefinitions);
    return (string) (($last['short'] ?? '') ?: 'Genesis');
}

function ygo_compute_standings(PDO $pdo, string $phase = 'league'): array
{
    $rows = $pdo->query("
        SELECT d.id, d.name, d.seed_rank, dv.code AS division_code
        FROM sx_ygo_decks d
        JOIN sx_ygo_divisions dv ON dv.id = d.division_id
        WHERE d.active = 1
        ORDER BY d.seed_rank, d.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $standings = [];
    foreach ($rows as $deck) {
        $standings[(int) $deck['id']] = [
            'id' => (int) $deck['id'],
            'name' => (string) $deck['name'],
            'division' => (string) $deck['division_code'],
            'rank' => (int) $deck['seed_rank'],
            'pts' => 0,
            'pj' => 0,
            'pg' => 0,
            'pe' => 0,
            'pp' => 0,
            'cg' => 0,
            'cp' => 0,
            'dp' => 0,
        ];
    }
    $stmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result, r.score
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.phase = ? AND m.status = 'FINALIZADA'
    ");
    $stmt->execute([$phase]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $match) {
        $aId = (int) $match['deck_a_id'];
        $bId = (int) $match['deck_b_id'];
        if (!isset($standings[$aId], $standings[$bId])) {
            continue;
        }
        $scoreParts = array_map('intval', explode('-', (string) $match['score']));
        $scoreA = $scoreParts[0] ?? 0;
        $scoreB = $scoreParts[1] ?? 0;
        $standings[$aId]['pj']++;
        $standings[$bId]['pj']++;
        $standings[$aId]['cg'] += $scoreA;
        $standings[$aId]['cp'] += $scoreB;
        $standings[$bId]['cg'] += $scoreB;
        $standings[$bId]['cp'] += $scoreA;
        if ($match['result'] === 'A') {
            $standings[$aId]['pts'] += 3;
            $standings[$aId]['pg']++;
            $standings[$bId]['pp']++;
        } elseif ($match['result'] === 'B') {
            $standings[$bId]['pts'] += 3;
            $standings[$bId]['pg']++;
            $standings[$aId]['pp']++;
        } elseif ($match['result'] === 'E') {
            $standings[$aId]['pts']++;
            $standings[$bId]['pts']++;
            $standings[$aId]['pe']++;
            $standings[$bId]['pe']++;
        }
    }
    foreach ($standings as &$row) {
        $row['dp'] = $row['cg'] - $row['cp'];
    }
    unset($row);
    usort($standings, static fn(array $a, array $b): int => $b['pts'] <=> $a['pts'] ?: $b['dp'] <=> $a['dp'] ?: $b['cg'] <=> $a['cg'] ?: $a['rank'] <=> $b['rank']);
    return $standings;
}
