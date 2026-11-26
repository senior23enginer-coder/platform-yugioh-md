<?php
declare(strict_types=1);
require_once __DIR__.'/event_teams.php';
require_once __DIR__.'/statistics_store.php';

function ygo_medal_definitions(): array
{
    return \StationX\Yugioh\Domain\Statistics\AchievementPolicy::definitions();
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
