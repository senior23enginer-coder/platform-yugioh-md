<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

/** Builds comparisons from supplied computed metrics using the existing formulas and sample policy. */
final class Analytics
{
    public static function analytics(array $data, int $minimum = 3): array
    {
        $definitions = [
            [
                'official_points',
                'Puntos oficiales acumulados',
                'puntos',
                false,
                'Suma literal de la fuente; escalas distintas no comparables',
            ],
            [
                'rating',
                'Rating Global por formato',
                'rating',
                false,
                'Ledger permanente; base 1000, divisor 600',
            ],
            ['power5', 'Power últimas 5 partidas', '%', false, '100*(3V+E)/(3k), k<=5'],
            ['power10', 'Power últimas 10 partidas', '%', false, '100*(3V+E)/(3k), k<=10'],
            [
                'confidence',
                'Consistencia conservadora',
                '%',
                false,
                '100*Wilson inferior; éxito V+0.5E; z=1.96',
            ],
            [
                'rival_strength',
                'Fuerza histórica de rivales',
                'rating',
                false,
                'Promedio del rating rival previo a cada match',
            ],
            ['V', 'Matches ganados', 'matches', false, 'Victorias confirmadas'],
            ['WR', 'Victorias de matches', '%', false, '100V/J'],
            ['VG', 'Duelos ganados', 'duelos', false, 'Victorias de duelos confirmados'],
            ['duel_diff', 'Diferencia de duelos', 'duelos', false, 'VG-DG'],
            [
                'first_wr',
                'WR empezando primero',
                '%',
                false,
                '100 victorias / duelos con iniciador propio confirmado',
            ],
            [
                'second_wr',
                'WR empezando segundo',
                '%',
                false,
                '100 victorias / duelos con iniciador rival confirmado',
            ],
            [
                'fewest_turns',
                'Victoria en menos turnos',
                'turnos',
                true,
                'Mínimo positivo entre victorias',
            ],
            [
                'fastest',
                'Victoria de menor duración',
                'segundos',
                true,
                'Mínimo positivo entre victorias',
            ],
            [
                'brick',
                'Más manos brick (frecuencia negativa)',
                'duelos',
                false,
                'Manos brick TRUE confirmadas',
            ],
            [
                'mean_turns',
                'Menor promedio de turnos',
                'turnos',
                true,
                'Suma turnos positivos / observaciones válidas',
            ],
            [
                'perfect_matches',
                'Matches sin duelos perdidos',
                'matches',
                false,
                'Match ganado con registro completo y cero DG',
            ],
            [
                'd5_wins',
                'Matches ganados con victoria en D5',
                'matches',
                false,
                'Match ganado y D5 ganado explícitamente',
            ],
        ];
        $rows = $data['global'] ?? [];
        $published = $data['publications'] ?? [];
        foreach ($rows as &$row) {
            $ownCuts = [];
            foreach ($published as $cut) {
                if ($cut['kind'] === 'global' && $cut['scope_key'] === $row['format_id']) {
                    foreach ($cut['data']['global'] ?? [] as $entry) {
                        if ($entry['team_id'] === $row['team_id']) {
                            $ownCuts[$cut['cut_at']] = [
                                'position' => $entry['position'],
                                'cut_id' => $cut['id'],
                            ];
                        }
                    }
                }
            }
            $values = array_values($ownCuts);
            if (count($values) >= 2) {
                $a = $values[count($values) - 2];
                $b = $values[count($values) - 1];
                if ($a['position'] !== null && $b['position'] !== null) {
                    $row['rise'] = max(0, $a['position'] - $b['position']);
                    $row['fall'] = max(0, $b['position'] - $a['position']);
                    $row['cut_evidence'] = [$a['cut_id'], $b['cut_id']];
                }
            }
        }
        unset($row);
        foreach ($rows as &$r) {
            foreach ([5, 10] as $n) {
                $recent = array_slice($r['recent'], -$n);
                $r['power' . $n] = $recent
                    ? (100 * array_sum(array_map(fn($v) => $v === 0.5 ? 1 : 3 * $v, $recent))) /
                        (3 * count($recent))
                    : null;
            }
            $r['confidence'] = $r['J']
                ? 100 *
                    \StationX\Yugioh\Domain\Statistics\RatingMath::wilson(
                        ($r['V'] + 0.5 * $r['E']) / $r['J'],
                        $r['J'],
                    )
                : null;
            $r['rival_strength'] = $r['J'] ? $r['rival_pre_sum'] / $r['J'] : null;
            $r['duel_diff'] = $r['VG'] - $r['DG'];
            $r['first_wr'] = \StationX\Yugioh\Domain\Statistics\RatingMath::ratio(
                $r['first_w'],
                $r['first_n'],
            );
            $r['second_wr'] = \StationX\Yugioh\Domain\Statistics\RatingMath::ratio(
                $r['second_w'],
                $r['second_n'],
            );
            $r['mean_turns'] = $r['turn_n'] ? $r['turn_sum'] / $r['turn_n'] : null;
            $r['brick_rate'] = \StationX\Yugioh\Domain\Statistics\RatingMath::ratio(
                $r['brick'],
                $r['brick_n'],
            );
            $r['brick_wr'] = \StationX\Yugioh\Domain\Statistics\RatingMath::ratio(
                $r['brick_w'],
                $r['brick'],
            );
            $r['lp_per_turn'] = $r['lp_per_turn_turns']
                ? $r['lp_per_turn_net'] / $r['lp_per_turn_turns']
                : null;
            $r['distinct_beaten'] = count($r['rivals_beaten']);
            $r['d5_wr'] = \StationX\Yugioh\Domain\Statistics\RatingMath::ratio(
                $r['d5_wins'],
                $r['d5_n'],
            );
            $r['debut'] = $r['recent']
                ? (100 * array_sum(array_slice($r['recent'], 0, 3))) / min(3, count($r['recent']))
                : null;
        }
        unset($r);
        $rankings = [];
        foreach ($definitions as [$key, $name, $unit, $ascending, $formula]) {
            $ranked = [];
            foreach ($rows as $r) {
                $n = match ($key) {
                    'first_wr' => $r['first_n'],
                    'second_wr' => $r['second_n'],
                    'VG', 'duel_diff' => $r['JD'],
                    'mean_turns' => $r['turn_n'],
                    'brick' => $r['brick_n'],
                    'power5' => min(5, $r['J']),
                    'power10' => min(10, $r['J']),
                    'fewest_turns' => $r['turn_win_n'] ?? 0,
                    'fastest' => $r['duration_win_n'] ?? 0,
                    default => $r['J'],
                };
                if (($r[$key] ?? null) === null || ($n < 1 && $key !== 'rating')) {
                    continue;
                }
                $ratio = in_array(
                    $key,
                    [
                        'WR',
                        'confidence',
                        'first_wr',
                        'second_wr',
                        'mean_turns',
                        'power5',
                        'power10',
                    ],
                    true,
                );
                if ($ratio && $n < $minimum) {
                    continue;
                }
                $universe = match ($key) {
                    'power5', 'power10' => $n,
                    'VG', 'duel_diff', 'mean_turns', 'brick', 'first_wr', 'second_wr' => $r['JD'],
                    'fastest', 'fewest_turns' => $r['VG'],
                    default => $r['J'],
                };
                $observed = in_array($key, ['first_wr', 'second_wr'], true)
                    ? $r['first_n'] + $r['second_n']
                    : $n;
                $ranked[] = [
                    'team_id' => $r['team_id'],
                    'deck_id' => $r['deck_id'],
                    'format_id' => $r['format_id'],
                    'value' => $r[$key],
                    'sample' => $n,
                    'coverage' => \StationX\Yugioh\Domain\Statistics\RatingMath::ratio(
                        $observed,
                        $universe,
                    ),
                ];
            }
            usort(
                $ranked,
                static fn($a, $b) => strcmp($a['format_id'], $b['format_id']) ?:
                (($ascending
                    ? $a['value'] <=> $b['value']
                    : $b['value'] <=> $a['value']) ?:
                $a['team_id'] <=> $b['team_id']),
            );
            $rankings[] = [
                'code' => $key,
                'name' => $name,
                'unit' => $unit,
                'formula' => $formula,
                'minimum_sample' => $minimum,
                'scope' => 'formato; solo resultados confirmados elegibles',
                'tie_break' => 'valor exacto, team_id ascendente',
                'rows' => $ranked,
            ];
        }
        $topDefs = [
            ['tiers', 'Rendimiento ante tiers históricos'],
            ['first_w', 'Duelos ganados empezando primero'],
            ['second_w', 'Duelos ganados empezando segundo'],
            ['comebacks', 'Remontadas tras perder D1'],
            ['perfect_matches', 'Matches sin duelos perdidos'],
            ['brick_rate', 'Menor tasa de brick'],
            ['brick_wr', 'WR con brick confirmado'],
            ['fastest', 'Victoria de duelo en menor tiempo'],
            ['fewest_turns', 'Victoria de duelo en menos turnos'],
            ['lp_net', 'Reducción neta acumulada de LP rival'],
            ['lp_per_turn', 'Reducción neta rival por turno'],
            ['distinct_beaten', 'Identidades rivales vencidas'],
            ['d5_wr', 'Rendimiento en D5'],
            ['debut', 'Mejor debut'],
            ['rise', 'Mayor subida entre cortes'],
            ['fall', 'Mayor caída entre cortes'],
            ['rivalry', 'Rivalidad más frecuente'],
            ['favorable', 'Matchup más favorable'],
            ['difficult', 'Matchup más difícil'],
        ];
        $tops = [];
        foreach ($topDefs as [$key, $name]) {
            $entries = [];
            if ($key === 'tiers') {
                foreach ($rows as $r) {
                    foreach ($r['versus_tiers'] ?? [] as $tier => $v) {
                        if ($v['J'] >= $minimum) {
                            $entries[] = [
                                'team_id' => $r['team_id'],
                                'format_id' => $r['format_id'],
                                'tier' => $tier,
                                'value' => (100 * $v['score']) / $v['J'],
                                'sample' => $v['J'],
                            ];
                        }
                    }
                }
            } elseif (in_array($key, ['rivalry', 'favorable', 'difficult'], true)) {
                foreach ($data['matchups'] ?? [] as $m) {
                    if ($m['J'] >= $minimum) {
                        $entries[] = array_merge($m, [
                            'value' => $key === 'rivalry' ? $m['J'] : $m['performance'],
                            'sample' => $m['J'],
                        ]);
                    }
                }
            } else {
                foreach ($rows as $r) {
                    $n = match ($key) {
                        'brick_rate' => $r['brick_n'],
                        'brick_wr' => $r['brick'],
                        'd5_wr' => $r['d5_n'],
                        'debut' => min(3, $r['J']),
                        'first_w' => $r['first_n'],
                        'second_w' => $r['second_n'],
                        'fastest' => $r['duration_win_n'] ?? 0,
                        'fewest_turns' => $r['turn_win_n'] ?? 0,
                        'lp_net' => $r['lp_n'],
                        'lp_per_turn' => $r['lp_turn_n'] ?? 0,
                        default => $r['J'],
                    };
                    if (($r[$key] ?? null) !== null && $n >= $minimum) {
                        $entries[] = [
                            'team_id' => $r['team_id'],
                            'format_id' => $r['format_id'],
                            'value' => $r[$key],
                            'sample' => $n,
                            'cut_evidence' => $r['cut_evidence'] ?? null,
                        ];
                    }
                }
            }
            $asc = in_array($key, ['brick_rate', 'fastest', 'fewest_turns', 'difficult'], true);
            usort(
                $entries,
                fn($a, $b) => strcmp($a['format_id'], $b['format_id']) ?:
                (($asc
                    ? $a['value'] <=> $b['value']
                    : $b['value'] <=> $a['value']) ?:
                ($a['team_id'] ?? $a['a']) <=> ($b['team_id'] ?? $b['a'])),
            );
            $units = [
                'tiers' => '%',
                'first_w' => 'duelos',
                'second_w' => 'duelos',
                'comebacks' => 'matches',
                'perfect_matches' => 'matches',
                'brick_rate' => '%',
                'brick_wr' => '%',
                'fastest' => 'segundos',
                'fewest_turns' => 'turnos',
                'lp_net' => 'LP netos',
                'lp_per_turn' => 'LP netos/turno',
                'distinct_beaten' => 'rivales',
                'd5_wr' => '%',
                'debut' => '%',
                'rise' => 'puestos',
                'fall' => 'puestos',
                'rivalry' => 'matches',
                'favorable' => '%',
                'difficult' => '%',
            ];
            $tops[] = [
                'code' => $key,
                'name' => $name,
                'unit' => $units[$key],
                'direction' => $asc ? 'menor' : 'mayor',
                'scope' => 'resultados confirmados elegibles; formato y corte declarados',
                'rows' => $entries,
                'status' => $entries ? 'CALCULADO' : 'PENDIENTE / MUESTRA INSUFICIENTE',
                'minimum_sample' => $minimum,
            ];
        }
        return ['rankings' => $rankings, 'tops' => $tops];
    }
}
