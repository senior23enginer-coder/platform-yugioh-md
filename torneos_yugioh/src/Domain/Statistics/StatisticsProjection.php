<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class StatisticsProjection
{
    public static function exportRows(array $data, string $collection = 'global'): array
    {
        if ($collection === 'ffa_ranking') {
            $data['ffa_ranking'] = $data['free_for_all']['ranking'] ?? [];
        }
        if ($collection === 'ffa_ledger') {
            $data['ffa_ledger'] = [];
            foreach ($data['free_for_all']['ledger'] ?? [] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $data['ffa_ledger'][] = array_merge($entry, $change);
                }
            }
        }
        if ($collection === 'profile') {
            $rows = [['Equipo/deck: historico confirmado; ratings separados por formato']];
            foreach (['global', 'profiles', 'medals', 'ffa_ranking'] as $section) {
                $rows[] = [strtoupper($section)];
                $rows = array_merge($rows, StatisticsProjection::exportRows($data, $section));
            }
            $rows[] = ['LOGROS: no conceden puntos de Ranking Global'];
            $seen = [];
            foreach ($data['global'] ?? [] as $g) {
                foreach ($g['achievements'] ?? [] as $a) {
                    $key =
                        $g['team_id'] .
                        ':' .
                        $a['code'] .
                        ($a['code'] === 'format_victory' ? ':' . $g['format_id'] : '');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $rows[] = [
                        $g['team_id'],
                        $a['name'],
                        $a['status'],
                        $a['progress'] ?? 'N/D',
                        $a['target'] ?? 'N/D',
                        $a['scope'] ?? '',
                        $data['version'] ?? StatisticsPolicy::VERSION,
                    ];
                }
            }
            return $rows;
        }
        if (in_array($collection, ['analytics', 'tops'], true)) {
            $rows = [
                ['indicador', 'formato', 'equipo_id', 'valor', 'muestra', 'unidad', 'corte', 'version'],
            ];
            foreach (
                $data['analytics'][$collection === 'tops' ? 'tops' : 'rankings'] ?? []
                as $metric
            ) {
                foreach ($metric['rows'] as $entry) {
                    $rows[] = [
                        $metric['name'],
                        $entry['format_id'],
                        $entry['team_id'] ?? ($entry['a'] ?? null),
                        $entry['value'],
                        $entry['sample'],
                        $metric['unit'] ?? 'ver indicador',
                        $data['cutoff'] ?? null,
                        $data['version'] ?? StatisticsPolicy::VERSION,
                    ];
                }
            }
            return $rows;
        }
        $fields = [
            'global' => [
                'format_id',
                'team_id',
                'deck_id',
                'rating',
                'bonus_medallas',
                'total',
                'state',
                'J',
                'position',
            ],
            'metagame' => [
                'season_id',
                'format_id',
                'deck_id',
                'tier',
                'IC',
                'R',
                'EL',
                'C',
                'RE',
                'RD',
                'match_WR',
                'J',
                'JD',
                'quality_status',
            ],
            'medals' => [
                'event_id',
                'name',
                'format_id',
                'team_id',
                'deck_id',
                'bonus_points',
                'bonus_version',
                'status',
            ],
            'ledger' => [
                'match_id',
                'source_key',
                'kind',
                'seat',
                'format_id',
                'team_a_id',
                'team_b_id',
                'deck_a_id',
                'deck_b_id',
                'played_at',
                'before_a',
                'before_b',
                'expectation_a',
                'k',
                'delta_a',
                'delta_b',
                'after_a',
                'after_b',
                'participants',
            ],
            'ffa_ranking' => [
                'format_id',
                'team_id',
                'deck_id',
                'rating',
                'state',
                'J',
                'V',
                'D',
                'WR',
                'position',
            ],
            'ffa_ledger' => [
                'match_id',
                'event_id',
                'season_id',
                'format_id',
                'played_at',
                'winner',
                'deck_id',
                'team_id',
                'before',
                'expectation',
                'score',
                'k',
                'delta',
                'after',
            ],
            'matchups' => ['format_id', 'a', 'b', 'J', 'V', 'E', 'D', 'WR', 'performance'],
            'players' => ['player_id', 'format_id', 'JD', 'VG', 'EG', 'DG'],
            'profiles' => [
                'team_id',
                'J',
                'V',
                'E',
                'D',
                'JD',
                'VG',
                'EG',
                'DG',
                'first_n',
                'first_w',
                'second_n',
                'second_w',
                'brick_n',
                'brick',
                'brick_w',
                'lp_n',
                'lp_net',
                'lp_received',
                'turn_n',
                'turn_sum',
                'duration_n',
                'duration_sum',
                'fastest',
                'fewest_turns',
            ],
            'pending' => ['match_id', 'reason'],
        ];
        if (!isset($fields[$collection])) {
            throw new InvalidArgumentException('Coleccion estadistica no exportable.');
        }
        $rows = [array_merge($fields[$collection], ['corte', 'version', 'estado_corte'])];
        foreach ($data[$collection] ?? [] as $entry) {
            $row = [];
            foreach ($fields[$collection] as $field) {
                $value = $entry[$field] ?? 'N/D';
                $row[] = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
                    : $value;
            }
            $rows[] = array_merge($row, [
                $data['cutoff'] ?? 'N/D',
                $data['version'] ?? StatisticsPolicy::VERSION,
                $data['status'] ?? 'PENDIENTE',
            ]);
        }
        return $rows;
    }

    public static function filter(array $matches, array $filters): array
    {
        $allowed = [
            'event_id',
            'season_id',
            'division_id',
            'phase',
            'event_type',
            'team_id',
            'deck_id',
            'player_id',
            'judge',
            'venue',
            'from',
            'to',
            'format_id',
        ];
        foreach (array_keys($filters) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Filtro no soportado: ' . $key);
            }
        }
        foreach (['from', 'to'] as $key) {
            if (!empty($filters[$key])) {
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
                if (!$d || $d->format('Y-m-d') !== $filters[$key]) {
                    throw new InvalidArgumentException('Fecha de filtro invalida.');
                }
            }
        }
        return array_values(
            array_filter($matches, static function ($m) use ($filters) {
                foreach ($filters as $key => $value) {
                    if ($value === '') {
                        continue;
                    }
                    if ($key === 'from' && substr($m['played_at'], 0, 10) < $value) {
                        return false;
                    }
                    if ($key === 'to' && substr($m['played_at'], 0, 10) > $value) {
                        return false;
                    }
                    if (in_array($key, ['from', 'to'], true)) {
                        continue;
                    }
                    if (in_array($key, ['team_id', 'deck_id'], true)) {
                        $ids = [
                            (int) ($m[str_replace('_id', '_a_id', $key)] ?? 0),
                            (int) ($m[str_replace('_id', '_b_id', $key)] ?? 0),
                        ];
                        foreach (
                            array_merge(
                                $m['participants_a'] ?? [],
                                $m['participants_b'] ?? [],
                                $m['players'] ?? [],
                            )
                            as $p
                        ) {
                            $ids[] = (int) $p[$key];
                        }
                        if (!in_array((int) $value, $ids, true)) {
                            return false;
                        }
                        continue;
                    }
                    if ($key === 'player_id') {
                        $found = false;
                        foreach ($m['duels'] ?? [] as $d) {
                            if (
                                in_array(
                                    (int) $value,
                                    [(int) ($d['player_a_id'] ?? 0), (int) ($d['player_b_id'] ?? 0)],
                                    true,
                                )
                            ) {
                                $found = true;
                            }
                        }
                        if (!$found) {
                            return false;
                        }
                        continue;
                    }
                    if (!array_key_exists($key, $m) || (string) $m[$key] !== $value) {
                        return false;
                    }
                }
                return true;
            }),
        );
    }
}
