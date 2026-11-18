<?php
declare(strict_types=1);

/**
 * teams: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_competition_defaults(string $type, string $ruleset, string $name): array
{
    $preset = [
        'qualifier' => ['suizo clasificatorio', '1v1', 5, 1, 'auto_swiss', 'all_active', 'Dos victorias consecutivas o ventaja al cierre D4/D5', 'Menor marcador al cierre del match', 'D5 empatado o dos empates consecutivos'],
        'tournament' => ['torneo competitivo', '1v1', 5, 1, 'manual', 'all_active', 'Victoria por match segun reglamento', 'Derrota por match segun reglamento', 'Empate segun reglamento'],
        'league' => ['liga todos contra todos', '1v1', 5, 1, 'auto_round_robin', 'division', 'Victoria por match segun D1-D5', 'Derrota por match segun D1-D5', 'Empate valido solo cuando el reglamento lo permita'],
        'cup' => ['eliminacion directa', '1v1', 5, 1, 'auto_knockout', 'qualified', 'Ganar el match para avanzar de ronda', 'Perder el match elimina o envia a llave secundaria', 'Empate genera desempate completo'],
        'world' => ['grupos y eliminacion directa', '1v1', 5, 1, 'manual', 'top_cut', 'Victoria suma en grupos o avanza en llave', 'Derrota suma en grupos o elimina en llave', 'Empate permitido en grupos; desempate en eliminatoria'],
        'final' => ['final unica', '1v1', 5, 1, 'manual', 'top_cut', 'Ganar la serie final', 'Perder la serie final', 'Empate fuerza desempate completo'],
        'friendly' => ['amistoso rotativo', '1v1', 5, 1, 'friendly_rotation', 'all_active', 'Resultado amistoso registrado en historial', 'Resultado amistoso registrado en historial', 'Empate amistoso permitido'],
        'special' => ['evento especial editable', '1v1', 5, 1, 'manual', 'manual', 'Condicion definida por el evento', 'Condicion definida por el evento', 'Condicion definida por el evento'],
    ][$type] ?? ['evento editable', '1v1', 5, 1, 'manual', 'manual', 'Condicion definida por el evento', 'Condicion definida por el evento', 'Condicion definida por el evento'];

    return [
        'format' => $preset[0],
        'match_format' => $preset[1],
        'best_of' => $preset[2],
        'participants_per_side' => $preset[3],
        'schedule_strategy' => $preset[4],
        'deck_pool' => $preset[5],
        'victory_condition' => $preset[6],
        'defeat_condition' => $preset[7],
        'draw_condition' => $preset[8],
        'rules' => $name . ' usa reglamento ' . strtoupper(str_replace('_', ' ', $ruleset)) . ' y reglas deportivas editables por evento.',
    ];
}

function ygo_team_name_for_deck(string $deckName, ?string $archetype = null): string
{
    return ygo_team_profile_for_deck($deckName, $archetype)['name'];
}

function ygo_team_profile_for_deck(string $deckName, ?string $archetype = null): array
{
    $base = trim((string) ($archetype ?: $deckName));
    $base = preg_replace('/\s+/', ' ', $base) ?: 'Duel';
    $normalized = strtolower($base);
    $profiles = [
        'kewl tune' => ['Neon Pulse Syndicate', 'NRS', '#21f6ff', '#ffde59', 'NR'],
        'branded' => ['Crimson Exiles', 'CAL', '#dc2626', '#f7d154', 'CA'],
        'vanquish soul k9' => ['Soulhound Vanguard', 'SHK', '#2563eb', '#f97316', 'K9'],
        'mitsurugi pure' => ['Crimson Shogunate', 'MSG', '#ef4444', '#111827', 'MS'],
        'elfnote' => ['Ethereal Symphony', 'ENS', '#22c55e', '#f0abfc', 'ES'],
        'chaos ritual' => ['Abyssal Ascendants', 'CHA', '#7c3aed', '#facc15', 'CH'],
        'dracotail' => ['Drakecourt Dominion', 'DTD', '#0891b2', '#f59e0b', 'DD'],
        'doomz' => ['Graveborn Revenants', 'DZR', '#4b5563', '#a855f7', 'DZ'],
        'ryzeal' => ['Nova Reactor', 'RZO', '#facc15', '#2563eb', 'RZ'],
        'maliss' => ['Darknet Protocol', 'MLP', '#ec4899', '#111827', 'MP'],
        'tearlaments' => ['Abyssal Elegy', 'TAB', '#0ea5e9', '#312e81', 'TA'],
        'snake-eyes' => ['Crimson-Eyed Serpents', 'SCE', '#ef4444', '#f97316', 'SE'],
        'fire king' => ['Inferno Crown', 'IFK', '#f97316', '#fde047', 'IK'],
        'unchained' => ['Broken Chain Legion', 'UCD', '#991b1b', '#64748b', 'UD'],
        'purrely' => ['Celestial Companions', 'PNV', '#f9a8d4', '#38bdf8', 'PN'],
        'swordsoul' => ['Celestial Blades', 'SSD', '#60a5fa', '#e5e7eb', 'SD'],
        'sky striker' => ['Aerial Valkyries', 'SSV', '#3b82f6', '#ef4444', 'SV'],
        'doomz artmage' => ['Arcane Deathcallers', 'DZA', '#9333ea', '#f59e0b', 'DA'],
        'white forest azamina' => ['Frostveil Covenant', 'AFW', '#e0f2fe', '#22c55e', 'AF'],
        'kashtira pure' => ['Astral Conquerors', 'KSC', '#dc2626', '#111827', 'KC'],
        'demonherrero bystial' => ['Infernal Forgelords', 'BBS', '#f97316', '#1f2937', 'BB'],
        'cyber dragon k9' => ['Chrome Hound Unit', 'CHK', '#06b6d4', '#d1d5db', 'CK'],
        'invoked' => ['Arcane Summoners', 'IVA', '#8b5cf6', '#f8fafc', 'IA'],
        'mathmech' => ['Binary Singularity', 'MTS', '#14b8a6', '#facc15', 'MS'],
        'infernoble' => ['Flamebound Paladins', 'INO', '#dc2626', '#fbbf24', 'IO'],
        'memento' => ['Deathbound Archive', 'MMR', '#64748b', '#c084fc', 'MM'],
        'exosister' => ['Sacred Exorcist Order', 'EXC', '#f472b6', '#ffffff', 'EC'],
        'salamangreat' => ['Solar Inferno', 'SGI', '#ef4444', '#f97316', 'SI'],
        'k9 pure' => ['Blackwatch Unit', 'KBO', '#111827', '#38bdf8', 'K9'],
        'kashtira demonherrero' => ['Voidforge Legion', 'KFM', '#b91c1c', '#f97316', 'KF'],
        'resonator rda horus' => ['Crimson Sun Tyrants', 'HCR', '#b91c1c', '#facc15', 'HR'],
        'crystron' => ['Quantum Sentinels', 'CRN', '#38bdf8', '#94a3b8', 'CN'],
        'vanquish soul zoodiac' => ['Zodiac Brawlers', 'VZC', '#ef4444', '#22c55e', 'VZ'],
        'exodia' => ['Fivefold Ascension', 'FBO', '#d97706', '#fef3c7', 'FO'],
        'blue dragon' => ['Azure Sovereigns', 'ADL', '#2563eb', '#bfdbfe', 'AD'],
        'dark world' => ['Underworld Renegades', 'DWO', '#1f2937', '#a855f7', 'DW'],
        'resonator red dragon archfiend' => ['Scarlet Tyrants', 'RDD', '#dc2626', '#111827', 'RD'],
        'traptrix ragnaraika' => ['Thornveil Brood', 'TVS', '#16a34a', '#f472b6', 'TS'],
        'cyber dragon pure' => ['Ironfang Rebellion', 'CDA', '#06b6d4', '#e5e7eb', 'CD'],
        'six samurai' => ['Last Shogun Clan', 'SSC', '#dc2626', '#facc15', 'SC'],
        'barrera de hielo' => ['Glacial Wardens', 'FRB', '#38bdf8', '#ffffff', 'FB'],
        'volcanic' => ['Ashen Cataclysm', 'VLE', '#ef4444', '#f97316', 'VE'],
        'horus' => ['Sunborn Dynasty', 'HSE', '#f59e0b', '#fde68a', 'HS'],
    ];
    $profile = $profiles[$normalized] ?? [$base . ' Club', strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $base), 0, 3)) ?: 'YMD', '#36d8ff', '#ffd25f', strtoupper(substr($base, 0, 2))];
    return [
        'name' => $profile[0],
        'code' => $profile[1],
        'primary' => $profile[2],
        'secondary' => $profile[3],
        'symbol' => $profile[4],
    ];
}

function ygo_find_or_create_team(PDO $pdo, string $teamName, ?array $profile = null): int
{
    $stmt = $pdo->prepare('SELECT id FROM sx_ygo_teams WHERE name = ? LIMIT 1');
    $stmt->execute([$teamName]);
    $teamId = (int) $stmt->fetchColumn();
    if ($teamId > 0) {
        $pdo->prepare('UPDATE sx_ygo_teams SET active = 1 WHERE id = ?')->execute([$teamId]);
        if ($profile) {
            $pdo->prepare('UPDATE sx_ygo_teams SET short_code=?, primary_color=?, secondary_color=?, symbol=? WHERE id=?')
                ->execute([$profile['code'], $profile['primary'], $profile['secondary'], $profile['symbol'], $teamId]);
        }
        return $teamId;
    }

    $pdo->prepare("INSERT INTO sx_ygo_teams (name, city, short_code, primary_color, secondary_color, symbol, active) VALUES (?, 'Tulua', ?, ?, ?, ?, 1)")
        ->execute([$teamName, $profile['code'] ?? null, $profile['primary'] ?? null, $profile['secondary'] ?? null, $profile['symbol'] ?? null]);
    return (int) $pdo->lastInsertId();
}

function ygo_sync_deck_teams(PDO $pdo): int
{
    $rows = $pdo->query("
        SELECT id, name, archetype, team_id
        FROM sx_ygo_decks
        WHERE active = 1
        ORDER BY seed_rank, id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    $update = $pdo->prepare('UPDATE sx_ygo_decks SET team_id = ? WHERE id = ?');
    foreach ($rows as $row) {
        if (!empty($row['team_id'])) {
            continue;
        }
        $profile = ygo_team_profile_for_deck((string) $row['name'], (string) ($row['archetype'] ?? ''));
        $teamId = ygo_find_or_create_team($pdo, $profile['name'], $profile);
        $update->execute([$teamId, (int) $row['id']]);
        $updated++;
    }

    $legacyTeamNames = ['Station X Titans', 'Tulua Duel Team', 'Meta Lab', 'Rogue Masters', 'Duel Academy'];
    $archive = $pdo->prepare("
        UPDATE sx_ygo_teams t
        SET t.active = 0
        WHERE t.name = ?
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_decks d
              WHERE d.team_id = t.id
                AND d.active = 1
          )
    ");
    foreach ($legacyTeamNames as $teamName) {
        $archive->execute([$teamName]);
    }
    $pdo->exec("
        UPDATE sx_ygo_teams t
        SET t.active = 0
        WHERE t.name LIKE 'Tulu% Duel Team'
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_decks d
              WHERE d.team_id = t.id
                AND d.active = 1
          )
    ");
    $pdo->exec("
        UPDATE sx_ygo_teams t
        SET t.active = 0
        WHERE t.name LIKE 'Equipo %'
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_decks d
              WHERE d.team_id = t.id
                AND d.active = 1
          )
    ");

    return $updated;
}
