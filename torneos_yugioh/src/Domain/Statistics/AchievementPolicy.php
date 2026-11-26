<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class AchievementPolicy
{
    public static function definitions(): array
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

    public static function achievements(
    array $r,
    array $events = [],
    array $honors = [],
    array $matches = [],
): array
    {
        $counts = [
            'match_wins' => $r['V'],
            'duel_wins' => $r['VG'],
            'rivals_beaten' => count($r['rivals_beaten']),
            'perfect_matches' => $r['perfect_matches'],
            'flawless_wins' => $r['flawless'],
            'best_streak' => $r['best_streak'],
            'd5_wins' => $r['d5_wins'],
            'brick_wins' => $r['brick_w'],
            'comebacks' => $r['comebacks'],
            'reverse_sweeps' => $r['reverse_sweeps'],
            'second_wins' => $r['second_w'],
            'surrender_wins' => $r['surrender_w'],
            'time_wins' => $r['time_w'],
            'under_10' => $r['under_10'],
            'under_5' => $r['under_5'],
            'under_5_turns' => $r['under_5_turns'],
            'under_3_turns' => $r['under_3_turns'],
            'deck_out_wins' => $r['deck_out_w'],
            'effect_wins' => $r['effect_w'],
            'dice_wins' => $r['dice_w'],
            'damage_dealt' => $r['lp_net'],
            'events_won' => count(array_filter(array_keys($r['events_won_match']))),
            'formats_won' => count($r['formats'] ?? []),
            'no_surrender_season' => null,
            'undefeated_champion' => null,
            'tier_zero_wins' => $r['tier_zero_wins'],
            'tiers_beaten' => count($r['tiers_beaten']),
        ];
        foreach ($honors as $h) {
            if (!empty($h['confirmed']) && (int) ($h['champion_team_id'] ?? 0) === $r['team_id']) {
                $own = array_values(
                    array_filter(
                        $matches,
                        fn($m) => StatisticsPolicy::eligible($m) === null &&
                            ($m['event_id'] ?? 0) === $h['competition_id'] &&
                            in_array($r['team_id'], [$m['team_a_id'], $m['team_b_id']], true),
                    ),
                );
                if (
                    $own &&
                    !array_filter(
                        $own,
                        fn($m) => $m['result'] !== 'E' &&
                            $m['result'] !== ($m['team_a_id'] === $r['team_id'] ? 'A' : 'B'),
                    )
                ) {
                    $counts['undefeated_champion'] = 1;
                }
            }
        }
        $closed = [];
        foreach ($events as $e) {
            if (
                in_array(
                    $e['season_status'] ?? '',
                    ['closed', 'completed', 'finalizada', 'finalizado'],
                    true,
                )
            ) {
                $closed[(int) $e['season_id']] = true;
            }
        }
        foreach (array_keys($closed) as $seasonId) {
            $won = 0;
            $surrender = false;
            $complete = true;
            $played = 0;
            foreach ($matches as $m) {
                if ((int) ($m['season_id'] ?? 0) !== $seasonId || StatisticsPolicy::eligible($m) !== null) {
                    continue;
                }
                $side =
                    $m['team_a_id'] === $r['team_id']
                        ? 'A'
                        : ($m['team_b_id'] === $r['team_id']
                            ? 'B'
                            : null);
                if ($side === null) {
                    continue;
                }
                $played++;
                foreach ($m['duels'] ?? [] as $d) {
                    if (empty($d['defeat_method'])) {
                        $complete = false;
                    }
                    if (($d['winner'] ?? '') === $side) {
                        $won++;
                    } elseif (
                        ($d['winner'] ?? '') !== 'E' &&
                        ($d['defeat_method'] ?? '') === 'surrender'
                    ) {
                        $surrender = true;
                    }
                }
                if (empty($m['duels_complete'])) {
                    $complete = false;
                }
            }
            if ($played && $complete) {
                $counts['no_surrender_season'] =
                    $won >= 5 && !$surrender ? 1 : $counts['no_surrender_season'] ?? 0;
            }
        }
        $out = [];
        foreach (AchievementPolicy::definitions() as $index => $d) {
            [$code, $name, $rarity, $field, $target] = $d;
            if ($code === 'three_decks') {
                $code = 'three_events';
                $name = 'Victoria en tres eventos';
                $field = 'events_won';
            }
            $v = $counts[$field] ?? null;
            $unlocked = $v !== null && $v >= $target;
            $secret = !empty($d[5]);
            if ($secret && !$unlocked && !empty($r['tier_unknown_wins'])) {
                $v = null;
            }
            $requiredCoverage = match ($field) {
                'flawless_wins' => ['lp_history_win_known_n', 'VG'],
                'brick_wins' => ['brick_win_known_n', 'VG'],
                'second_wins' => null,
                'under_10', 'under_5' => ['duration_win_n', 'VG'],
                'under_5_turns', 'under_3_turns' => ['turn_win_n', 'VG'],
                'surrender_wins', 'time_wins', 'deck_out_wins', 'effect_wins' => [
                    'closure_win_known_n',
                    'VG',
                ],
                'damage_dealt' => ['lp_n', 'JD'],
                default => null,
            };
            if (
                !$unlocked &&
                $requiredCoverage &&
                ($r[$requiredCoverage[0]] ?? 0) < $r[$requiredCoverage[1]]
            ) {
                $v = null;
            }
            if (!$unlocked && $field === 'second_wins' && $r['first_n'] + $r['second_n'] < $r['JD']) {
                $v = null;
            }
            $out[] = [
                'code' => $code,
                'name' => $secret && !$unlocked ? 'Logro secreto' : $name,
                'rarity' => $rarity,
                'category' => ['Trayectoria', 'Dominio', 'Resiliencia', 'Proezas', 'Adicionales'][
                    intdiv($index, 8)
                ],
                'entity_type' => 'team',
                'scope' => 'identidad equipo; histórico elegible',
                'unit' => $secret && !$unlocked ? null : $field,
                'target' => $secret && !$unlocked ? null : $target,
                'progress' => $secret && !$unlocked ? null : $v,
                'earned_moment' => $unlocked ? $r['achievement_moments'][$code] ?? null : null,
                'status' => $unlocked
                    ? 'DESBLOQUEADO'
                    : ($v === null
                        ? 'REVISION PENDIENTE'
                        : 'BLOQUEADO'),
                'evidence' => $unlocked ? $r['evidence'] : [],
                'version' => StatisticsPolicy::VERSION,
            ];
        }
        $out[] = [
            'code' => 'format_victory',
            'name' => 'Primera victoria en ' . $r['format_id'],
            'rarity' => 'silver',
            'scope' => 'equipo/formato',
            'unit' => 'match_wins',
            'target' => 1,
            'progress' => $r['V'],
            'status' => $r['V'] > 0 ? 'DESBLOQUEADO' : 'BLOQUEADO',
            'evidence' => $r['V'] > 0 ? $r['evidence'] : [],
            'version' => StatisticsPolicy::VERSION,
        ];
        return $out;
    }
}
