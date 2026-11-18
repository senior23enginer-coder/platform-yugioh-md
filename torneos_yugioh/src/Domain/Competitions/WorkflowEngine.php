<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

/** Deterministic round generation, ranking and result acceptance for the existing event models. */
final class WorkflowEngine
{
    public static function ranking(array $s, ?array $only = null, ?string $phase = null): array
    {
        $ids = $only ?? $s['roster'];
        $rows = [];
        foreach ($ids as $id) {
            $rows[$id] = [
                'id' => $id,
                'seed' => array_search($id, $s['roster'], true),
                'points' => 0,
                'buchholz' => 0,
                'game_difference' => 0,
                'played' => 0,
                'opponents' => [],
            ];
        }
        foreach ($s['history'] as $m) {
            if ($phase !== null && $m['phase'] !== $phase) {
                continue;
            }
            [$a, $b] = [$m['a'], $m['b']];
            if (!isset($rows[$a], $rows[$b])) {
                continue;
            }
            $winner = $m['winner'];
            $rows[$a]['played']++;
            $rows[$b]['played']++;
            $points = $s['policy']['points'];
            $rows[$a]['points'] +=
                $winner === 'E'
                    ? $points['draw']
                    : ($winner === 'A'
                        ? $points['win']
                        : $points['loss']);
            $rows[$b]['points'] +=
                $winner === 'E'
                    ? $points['draw']
                    : ($winner === 'B'
                        ? $points['win']
                        : $points['loss']);
            $rows[$a]['opponents'][] = $b;
            $rows[$b]['opponents'][] = $a;
            $diff = (int) ($m['game_difference'] ?? 0);
            $rows[$a]['game_difference'] += $diff;
            $rows[$b]['game_difference'] -= $diff;
        }
        if ($phase === null || $phase === 'swiss') {
            foreach ($s['byes'] as $b) {
                if (isset($rows[$b['id']])) {
                    $rows[$b['id']]['points'] += $s['policy']['points']['bye'];
                }
            }
        }
        foreach ($rows as &$r) {
            foreach ($r['opponents'] as $op) {
                $r['buchholz'] += $rows[$op]['points'];
            }
        }
        unset($r);
        usort(
            $rows,
            static fn($a, $b) => [
                $b['points'],
                $b['buchholz'],
                $b['game_difference'],
                $a['seed'],
            ] <=> [$a['points'], $a['buchholz'], $a['game_difference'], $b['seed']],
        );
        return $rows;
    }

    public static function pair(array $ranked, array $forbidden = [], int &$budget = 100000): ?array
    {
        if (!$ranked) {
            return [];
        }
        if (--$budget < 0) {
            throw new InvalidArgumentException(
                'Emparejamiento demasiado complejo; no se creara una ronda parcial.',
            );
        }
        $a = array_shift($ranked);
        foreach ($ranked as $i => $b) {
            $key = min($a, $b) . ':' . max($a, $b);
            if (isset($forbidden[$key])) {
                continue;
            }
            $rest = $ranked;
            array_splice($rest, $i, 1);
            $pairs = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::pair(
                $rest,
                $forbidden,
                $budget,
            );
            if ($pairs !== null) {
                return array_merge([[$a, $b]], $pairs);
            }
        }
        return null;
    }

    public static function rr(array $ids): array
    {
        if (count($ids) % 2) {
            $ids[] = null;
        }
        $rounds = [];
        $n = count($ids);
        for ($r = 0; $r < $n - 1; $r++) {
            $pairs = [];
            for ($i = 0; $i < $n / 2; $i++) {
                if ($ids[$i] !== null && $ids[$n - 1 - $i] !== null) {
                    $pairs[] = [$ids[$i], $ids[$n - 1 - $i]];
                }
            }
            $rounds[] = $pairs;
            $tail = array_pop($ids);
            array_splice($ids, 1, 0, [$tail]);
        }
        return $rounds;
    }

    public static function ladderOrder(array $s): array
    {
        $ids = $s['roster'];
        foreach ($s['history'] as $m) {
            if ($m['phase'] === 'ladder') {
                $a = array_search($m['a'], $ids, true);
                $b = array_search($m['b'], $ids, true);
                if ($a === false || $b !== $a + 1) {
                    throw new InvalidArgumentException(
                        'Historial de retos incompatible con la escalera.',
                    );
                }
                if ($m['winner'] === 'B') {
                    [$ids[$a], $ids[$b]] = [$ids[$b], $ids[$a]];
                }
            }
        }
        return $ids;
    }

    public static function next(array $s): array
    {
        if (!empty($s['pending'])) {
            throw new InvalidArgumentException('Resuelve la etapa actual antes de generar otra.');
        }
        if ($s['champion'] !== null) {
            return ['state' => $s, 'matches' => [], 'complete' => true];
        }
        $model = $s['policy']['model'];
        $round = $s['round'] + 1;
        $matches = [];
        $add = static function (
            int $a,
            int $b,
            string $phase,
            string $side = 'main',
            ?int $group = null,
        ) use (&$matches, $round): void {
            $matches[] = [
                'a' => $a,
                'b' => $b,
                'phase' => $phase,
                'side' => $side,
                'round' => $round,
                'group' => $group,
            ];
        };
        $ko = static function (array $ids, string $phase) use (&$s, $round, $add): void {
            $n = count($ids);
            for ($i = 0; $i < intdiv($n, 2); $i++) {
                $add($ids[$i], $ids[$n - 1 - $i], $phase);
            }
            if ($n % 2) {
                $s['ko_byes'][$round] = $ids[intdiv($n, 2)];
            }
        };
        $lastPhase = $s['history'] ? $s['history'][array_key_last($s['history'])]['phase'] : '';
        if (in_array($model, ['king_of_the_hill', 'gauntlet'], true)) {
            $last = $s['history'] ? end($s['history']) : null;
            $defender = $last ? $last[$last['winner'] === 'A' ? 'a' : 'b'] : $s['roster'][0];
            $failed = $model === 'gauntlet' && $last && $last['winner'] === 'B';
            if ($failed || $round > count($s['roster']) - 1) {
                if ($failed) {
                    $s['gauntlet_success'] = false;
                    $s['gauntlet_stopped_by'] = $defender;
                } else {
                    $s['champion'] = $defender;
                    $s['runner_up'] = $last[$last['winner'] === 'A' ? 'b' : 'a'];
                    $s['gauntlet_success'] = $model === 'gauntlet';
                }
                return ['state' => $s, 'matches' => [], 'complete' => true];
            }
            $add($defender, $s['roster'][$round], $model);
        } elseif ($model === 'ladder') {
            $order = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ladderOrder($s);
            if ($round > $s['policy']['ladder_rounds']) {
                $s['champion'] = $order[0];
                $s['runner_up'] = $order[1];
                return ['state' => $s, 'matches' => [], 'complete' => true];
            }
            $offset = $round % 2 === 1 ? 0 : 1;
            if (count($order) === 2) {
                $offset = 0;
            }
            for ($i = $offset; $i + 1 < count($order); $i += 2) {
                $add($order[$i], $order[$i + 1], 'ladder');
            }
        } elseif ($model === 'flights' && $lastPhase !== 'playoff') {
            $winners = [];
            foreach ($s['groups'] as $g => $ids) {
                $lost = [];
                foreach ($s['history'] as $m) {
                    if ($m['phase'] === 'flight' && $m['group'] === $g) {
                        $lost[] = $m[$m['winner'] === 'A' ? 'b' : 'a'];
                    }
                }
                $alive = array_values(array_diff($ids, $lost));
                if (count($alive) === 1) {
                    $winners[] = $alive[0];
                } else {
                    for ($i = 0; $i < intdiv(count($alive), 2); $i++) {
                        $add(
                            $alive[$i],
                            $alive[count($alive) - 1 - $i],
                            'flight',
                            'flight_' . ($g + 1),
                            $g,
                        );
                    }
                }
            }
            if (!$matches) {
                if (count($winners) === 1) {
                    $s['champion'] = $winners[0];
                    $last = end($s['history']);
                    $s['runner_up'] = $last[$last['winner'] === 'A' ? 'b' : 'a'];
                    return ['state' => $s, 'matches' => [], 'complete' => true];
                }
                $s['qualified'] = $winners;
                $ko($winners, 'playoff');
            }
        } elseif (in_array($model, ['_teams_round_robin', '_teams_double_round_robin'], true)) {
            $rounds = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::rr($s['roster']);
            if ($model === '_teams_double_round_robin') {
                $rounds = array_merge(
                    $rounds,
                    array_map(
                        static fn($r) => array_map(static fn($p) => [$p[1], $p[0]], $r),
                        $rounds,
                    ),
                );
            }
            if (isset($rounds[$round - 1])) {
                foreach ($rounds[$round - 1] as [$a, $b]) {
                    $add($a, $b, 'league');
                }
            } else {
                $ranking = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking(
                    $s,
                    null,
                    'league',
                );
                $s['champion'] = $ranking[0]['id'];
                $s['runner_up'] = $ranking[1]['id'];
                return ['state' => $s, 'matches' => [], 'complete' => true];
            }
        } elseif ($model === '_teams_single_elimination' && $lastPhase === '') {
            $ko($s['roster'], 'playoff');
        } elseif ($model === 'series') {
            $wins = array_fill_keys($s['roster'], 0);
            foreach ($s['history'] as $m) {
                $wins[$m[$m['winner'] === 'A' ? 'a' : 'b']]++;
            }
            foreach ($wins as $id => $w) {
                if ($w >= $s['policy']['series_wins']) {
                    $s['champion'] = $id;
                    $s['runner_up'] = $s['roster'][0] === $id ? $s['roster'][1] : $s['roster'][0];
                    return ['state' => $s, 'matches' => [], 'complete' => true];
                }
            }
            $add($s['roster'][0], $s['roster'][1], 'series');
        } elseif ($model === 'double_elimination') {
            $losses = array_fill_keys($s['roster'], 0);
            foreach ($s['history'] as $m) {
                $losses[$m[$m['winner'] === 'A' ? 'b' : 'a']]++;
            }
            $upper = [];
            $lower = [];
            foreach ($s['roster'] as $id) {
                if ($losses[$id] === 0) {
                    $upper[] = $id;
                } elseif ($losses[$id] === 1) {
                    $lower[] = $id;
                }
            }
            $alive = array_merge($upper, $lower);
            if (count($alive) === 1) {
                $s['champion'] = $alive[0];
                $last = end($s['history']);
                $s['runner_up'] = $last[$last['winner'] === 'A' ? 'b' : 'a'];
                return ['state' => $s, 'matches' => [], 'complete' => true];
            }
            if (count($upper) === 1 && count($lower) === 1) {
                $add($upper[0], $lower[0], 'double_elimination', 'final');
            } elseif (!$upper && count($lower) === 2) {
                $add($lower[0], $lower[1], 'double_elimination', 'reset');
            } else {
                foreach (['upper' => $upper, 'lower' => $lower] as $side => $ids) {
                    for ($i = 0; $i + 1 < count($ids); $i += 2) {
                        $add($ids[$i], $ids[$i + 1], 'double_elimination', $side);
                    }
                }
            }
        } elseif (in_array($lastPhase, ['top_cut', 'playoff'], true)) {
            $w = [];
            foreach ($s['history'] as $m) {
                if ($m['round'] === $s['round']) {
                    $w[] = $m[$m['winner'] === 'A' ? 'a' : 'b'];
                }
            }
            if (isset($s['ko_byes'][$s['round']])) {
                $w[] = $s['ko_byes'][$s['round']];
            }
            if (count($w) === 1) {
                $s['champion'] = $w[0];
                $last = end($s['history']);
                $s['runner_up'] = $last[$last['winner'] === 'A' ? 'b' : 'a'];
                return ['state' => $s, 'matches' => [], 'complete' => true];
            }
            $ko($w, $lastPhase);
        } elseif (in_array($model, ['swiss', 'swiss_top_cut'], true)) {
            if ($round <= $s['policy']['swiss_rounds']) {
                $ranked = array_column(
                    \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking($s),
                    'id',
                );
                $forbidden = [];
                foreach ($s['history'] as $m) {
                    $forbidden[min($m['a'], $m['b']) . ':' . max($m['a'], $m['b'])] = true;
                }
                $pairs = null;
                $bye = null;
                if (count($ranked) % 2) {
                    $used = array_column($s['byes'], 'id');
                    foreach (array_reverse($ranked) as $candidate) {
                        if (in_array($candidate, $used, true)) {
                            continue;
                        }
                        $rest = array_values(array_diff($ranked, [$candidate]));
                        $budget = 100000;
                        $pairs = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::pair(
                            $rest,
                            $forbidden,
                            $budget,
                        );
                        if ($pairs !== null) {
                            $bye = $candidate;
                            break;
                        }
                    }
                } else {
                    $budget = 100000;
                    $pairs = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::pair(
                        $ranked,
                        $forbidden,
                        $budget,
                    );
                }
                if ($pairs === null) {
                    throw new InvalidArgumentException(
                        'No existe una ronda suiza completa sin repetir rival/BYE; revisa los resultados antes de continuar.',
                    );
                }
                if ($bye !== null) {
                    $s['byes'][] = ['id' => $bye, 'round' => $round];
                }
                foreach ($pairs as [$a, $b]) {
                    $add($a, $b, 'swiss');
                }
            } elseif ($model === 'swiss_top_cut') {
                $ko(
                    array_slice(
                        array_column(
                            \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking($s),
                            'id',
                        ),
                        0,
                        $s['policy']['top_n'],
                    ),
                    'top_cut',
                );
            } else {
                $ranking = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking($s);
                $s['champion'] = $ranking[0]['id'];
                $s['runner_up'] = $ranking[1]['id'];
                return ['state' => $s, 'matches' => [], 'complete' => true];
            }
        } elseif (in_array($model, ['groups', 'groups_knockout', 'season_playoffs'], true)) {
            $groups = $model === 'season_playoffs' ? [$s['roster']] : $s['groups'];
            $hasRound = false;
            foreach ($groups as $g => $ids) {
                $rr = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::rr($ids);
                if (isset($rr[$round - 1])) {
                    $hasRound = true;
                    foreach ($rr[$round - 1] as [$a, $b]) {
                        $add(
                            $a,
                            $b,
                            $model === 'season_playoffs' ? 'league' : 'groups',
                            'group_' . ($g + 1),
                            $g,
                        );
                    }
                }
            }
            if (!$hasRound) {
                if ($model === 'groups') {
                    $s['group_winners'] = [];
                    foreach ($groups as $g => $ids) {
                        $s['group_winners'][
                            $g
                        ] = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking(
                            $s,
                            $ids,
                            'groups',
                        )[0]['id'];
                    }
                    return ['state' => $s, 'matches' => [], 'complete' => true];
                }
                if ($model === 'season_playoffs') {
                    $qualified = array_slice(
                        array_column(
                            \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking(
                                $s,
                                null,
                                'league',
                            ),
                            'id',
                        ),
                        0,
                        $s['policy']['top_n'],
                    );
                } else {
                    $qualified = [];
                    $origin = [];
                    $position = [];
                    foreach ($groups as $g => $ids) {
                        foreach (
                            array_slice(
                                \StationX\Yugioh\Domain\Competitions\WorkflowEngine::ranking(
                                    $s,
                                    $ids,
                                    'groups',
                                ),
                                0,
                                $s['policy']['group_qualifiers'],
                            )
                            as $rank => $r
                        ) {
                            $qualified[] = $r['id'];
                            $origin[$r['id']] = $g;
                            $position[$r['id']] = $rank;
                        }
                    }
                    // Prefer cross-group opening matches. With a single group use seed versus reverse seed.
                    if (count($groups) > 1 && count($qualified) % 2 === 0) {
                        $forbidden = [];
                        foreach ($qualified as $a) {
                            foreach ($qualified as $b) {
                                if (
                                    $origin[$a] === $origin[$b] ||
                                    ($s['policy']['group_qualifiers'] === 2 &&
                                        $position[$a] === $position[$b])
                                ) {
                                    $forbidden[min($a, $b) . ':' . max($a, $b)] = true;
                                }
                            }
                        }
                        $budget = 100000;
                        $pairs = \StationX\Yugioh\Domain\Competitions\WorkflowEngine::pair(
                            $qualified,
                            $forbidden,
                            $budget,
                        );
                        if ($pairs === null) {
                            throw new InvalidArgumentException(
                                'No existe cruce compatible entre clasificados de grupos distintos.',
                            );
                        }
                        foreach ($pairs as [$a, $b]) {
                            $add($a, $b, 'playoff');
                        }
                    }
                }
                $s['qualified'] = $qualified;
                if (!$matches) {
                    $ko($qualified, 'playoff');
                }
            }
        }
        if (!$matches) {
            throw new LogicException('El motor no produjo una etapa valida.');
        }
        $s['round'] = $round;
        $s['pending'] = $matches;
        return ['state' => $s, 'matches' => $matches, 'complete' => false];
    }

    public static function accept(array $s, array $results): array
    {
        $pending = $s['pending'] ?? [];
        if (!$pending || count($results) !== count($pending)) {
            throw new InvalidArgumentException('La etapa debe tener todos sus resultados.');
        }
        foreach ($pending as $i => $m) {
            $r = $results[$i];
            $winner = $r['winner'] ?? '';
            if (!in_array($winner, ['A', 'B', 'E'], true)) {
                throw new InvalidArgumentException('Resultado pendiente o invalido.');
            }
            if ($winner === 'E' && !in_array($m['phase'], ['swiss', 'groups', 'league'], true)) {
                throw new InvalidArgumentException(
                    'Esta etapa requiere ganador; resuelve el empate en el mismo match.',
                );
            }
            $m['winner'] = $winner;
            $m['game_difference'] = (int) ($r['game_difference'] ?? 0);
            $s['history'][] = $m;
        }
        unset($s['pending']);
        return $s;
    }
}
