<?php
declare(strict_types=1);
require_once __DIR__.'/event_teams.php';
require_once __DIR__.'/statistics_store.php';

function ygo_medal_definitions(): array
{
    return [
        ['first_match_win', 'Primera victoria', 'common', 'match_wins', 1],
        ['three_match_wins', 'Tres partidas ganadas', 'common', 'match_wins', 3],
        ['five_match_wins', 'Cinco partidas ganadas', 'silver', 'match_wins', 5],
        ['ten_match_wins', 'Diez partidas ganadas', 'gold', 'match_wins', 10],
        ['five_duel_wins', 'Cinco duelos ganados', 'common', 'duel_wins', 5],
        ['ten_duel_wins', 'Diez duelos ganados', 'silver', 'duel_wins', 10],
        ['twenty_five_duel_wins', 'Veinticinco duelos ganados', 'gold', 'duel_wins', 25],
        ['three_rivals', 'Vencio a tres rivales distintos', 'silver', 'rivals_beaten', 3],
        ['first_sweep', 'Primera partida perfecta', 'silver', 'perfect_matches', 1],
        ['three_sweeps', 'Tres partidas perfectas', 'gold', 'perfect_matches', 3],
        ['flawless_win', 'Victoria sin perder LP', 'gold', 'flawless_wins', 1],
        ['three_flawless', 'Tres victorias sin perder LP', 'platinum', 'flawless_wins', 3],
        ['streak_three', 'Racha de tres partidas', 'silver', 'best_streak', 3],
        ['streak_five', 'Racha de cinco partidas', 'gold', 'best_streak', 5],
        ['d5_win', 'Victoria decisiva en D5', 'silver', 'd5_wins', 1],
        ['three_d5_wins', 'Tres victorias en D5', 'platinum', 'd5_wins', 3],
        ['brick_survivor', 'Supero una mano brick', 'silver', 'brick_wins', 1],
        ['brick_master', 'Supero tres manos brick', 'gold', 'brick_wins', 3],
        ['comeback', 'Primera remontada', 'silver', 'comebacks', 1],
        ['three_comebacks', 'Tres remontadas', 'gold', 'comebacks', 3],
        ['reverse_sweep', 'Remontada desde 0-2', 'legendary', 'reverse_sweeps', 1],
        ['second_three', 'Tres victorias empezando segundo', 'silver', 'second_wins', 3],
        ['forced_surrender', 'Forzo una rendicion', 'common', 'surrender_wins', 1],
        ['time_limit_win', 'Victoria al limite de tiempo', 'silver', 'time_wins', 1],
        ['under_ten_minutes', 'Victoria en menos de 10 min', 'silver', 'under_10', 1],
        ['under_five_minutes', 'Victoria en menos de 5 min', 'gold', 'under_5', 1],
        ['five_turn_win', 'Victoria en 5 turnos o menos', 'gold', 'under_5_turns', 1],
        ['three_turn_win', 'Victoria en 3 turnos o menos', 'platinum', 'under_3_turns', 1],
        ['deck_out_win', 'Victoria por deck sin cartas', 'silver', 'deck_out_wins', 1],
        ['effect_win', 'Victoria por efecto especial', 'gold', 'effect_wins', 1],
        ['three_dice_wins', 'Tres tiradas de dado ganadas', 'silver', 'dice_wins', 3],
        ['damage_50000', '50.000 LP de reduccion neta rival', 'gold', 'damage_dealt', 50000],
        ['three_formats', 'Victoria en tres formatos', 'platinum', 'formats_won', 3],
        ['three_events', 'Victorias en tres eventos distintos', 'platinum', 'events_won', 3],
        ['season_no_surrender', 'Temporada sin rendirse', 'platinum', 'no_surrender_season', 1],
        ['undefeated_champion', 'Campeon invicto', 'legendary', 'undefeated_champion', 1],
        ['tier_zero_slayer', 'Vencio a un Tier Zero', 'legendary', 'tier_zero_wins', 1, true],
        ['three_tiers', 'Vencio tres tiers diferentes', 'legendary', 'tiers_beaten', 3, true],
    ];
}

function ygo_empty_competitive_metric(): array
{
    return [
        'match_wins' => 0, 'match_losses' => 0, 'match_draws' => 0,
        'duel_wins' => 0, 'duel_losses' => 0, 'duel_draws' => 0,
        'perfect_matches' => 0, 'flawless_wins' => 0, 'best_streak' => 0, 'current_streak' => 0,
        'd5_wins' => 0, 'brick_wins' => 0, 'comebacks' => 0, 'reverse_sweeps' => 0,
        'second_wins' => 0, 'surrender_wins' => 0, 'surrender_losses' => 0, 'time_wins' => 0,
        'under_10' => 0, 'under_5' => 0, 'under_5_turns' => 0, 'under_3_turns' => 0,
        'deck_out_wins' => 0, 'effect_wins' => 0, 'dice_wins' => 0, 'damage_dealt' => 0,
        'no_surrender_season' => 0, 'undefeated_champion' => 0, 'tier_zero_wins' => 0,
        'rivals' => [], 'formats' => [], 'decks' => [], 'tiers' => [],
        'context' => [], 'earned' => [], 'format_context' => [],
    ];
}

function ygo_sync_medal_unlocks(PDO $pdo, int $triggerMatchId = 0): int
{
    return ygo_stats_sync($pdo);
}

function ygo_sync_meta_history(PDO $pdo): int
{
    return ygo_stats_sync($pdo);
}

function ygo_sync_elo_history(PDO $pdo): int
{
    return ygo_stats_sync($pdo);
}

function ygo_sync_global_ranking_history(PDO $pdo): int
{
    return ygo_stats_sync($pdo);
}

function ygo_sync_event_summaries(PDO $pdo, ?int $competitionId = null): int
{
    return 0;
}

function ygo_backfill_match_snapshots(PDO $pdo): int
{
    if (!function_exists('ygo_table_exists') || !ygo_table_exists($pdo, 'sx_ygo_match_snapshots')) return 0;
    $matches = $pdo->query("
        SELECT m.id, m.competition_id, m.division_id, m.deck_a_id, m.deck_b_id,
               r.d1, r.d2, r.d3, r.d4, r.d5, r.duel_details,
               c.event_ruleset, c.ruleset_version, dv.name AS division_name
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id=m.id AND r.result IN ('A','B','E')
        LEFT JOIN sx_ygo_competitions c ON c.id=m.competition_id
        LEFT JOIN sx_ygo_divisions dv ON dv.id=COALESCE(m.division_id,c.division_id)
        WHERE NOT EXISTS(SELECT 1 FROM sx_ygo_settings ts WHERE ts.setting_key=CONCAT('event_teams_',m.competition_id) AND ts.season_id=c.season_id)
        ORDER BY m.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $deckStmt = $pdo->prepare('SELECT d.*, t.name AS team_name FROM sx_ygo_decks d LEFT JOIN sx_ygo_teams t ON t.id=d.team_id WHERE d.id=? LIMIT 1');
    $cardsStmt = $pdo->prepare('SELECT card_name, role, quantity, section, notes FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY section,id');
    $insert = $pdo->prepare("
        INSERT IGNORE INTO sx_ygo_match_snapshots
            (match_id,duel_number,side,competition_id,deck_id,team_id,player_id,deck_name,archetype,variation,team_name,ruleset_code,ruleset_version,division_name,decklist_json,decklist_hash,data_origin,snapshot_status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'inferred','inferred')
    ");
    $count = 0;
    foreach ($matches as $match) {
        $details = json_decode((string) ($match['duel_details'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        foreach (['d1', 'd2', 'd3', 'd4', 'd5'] as $index => $duelField) {
            if (!in_array((string) ($match[$duelField] ?? ''), ['A', 'B', 'E'], true)) continue;
            $duelNumber = $index + 1;
            $detail = [];
            foreach ($details as $candidate) {
                if (is_array($candidate) && (int) ($candidate['duel'] ?? 0) === $duelNumber) { $detail = $candidate; break; }
            }
            foreach (['A', 'B'] as $side) {
                $suffix = strtolower($side);
                $deckId = (int) (($detail['deck_' . $suffix . '_id'] ?? 0) ?: $match['deck_' . $suffix . '_id']);
                if ($deckId <= 0) continue;
                $deckStmt->execute([$deckId]);
                $deck = $deckStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!$deck) continue;
                $cardsStmt->execute([$deckId]);
                $decklist = $cardsStmt->fetchAll(PDO::FETCH_ASSOC);
                $decklistJson = json_encode($decklist, JSON_UNESCAPED_UNICODE);
                $insert->execute([
                    (int) $match['id'], $duelNumber, $side, (int) ($match['competition_id'] ?? 0) ?: null,
                    $deckId, (int) ($deck['team_id'] ?? 0) ?: null, (int) ($detail['player_' . $suffix . '_id'] ?? 0) ?: null,
                    (string) ($deck['name'] ?? ''), (string) ($deck['archetype'] ?? ''), (string) ($deck['variations'] ?? ''),
                    (string) ($deck['team_name'] ?? ''), (string) ($match['event_ruleset'] ?? ''), (string) ($match['ruleset_version'] ?? ''),
                    (string) ($match['division_name'] ?? ''), $decklistJson, hash('sha256', $decklistJson ?: '[]'),
                ]);
                $count += $insert->rowCount();
            }
        }
    }
    return $count;
}
