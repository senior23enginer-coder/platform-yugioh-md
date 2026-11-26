<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Reports;

final class EntityReportProjection
{
    public static function build(
        string $export,
        array $query,
        array $allDecks,
        array $teams,
        array $competitions,
        array $fixtures,
        array $cards,
        array $media,
        array $appData,
        string $generatedAt,
    ): array {
        $rows = [['YUGIOH MD', 'Station X Tulua', $generatedAt]];
        $title = 'Reporte ' . ucfirst(str_replace('_', ' ', $export));
        $add = static function (array $row) use (&$rows): void {
            $rows[] = $row;
        };

        $add(['']);
        if ($export === 'entity_report') {
            $entityType = preg_replace(
                '/[^a-z_]/',
                '',
                strtolower((string) ($query['entity_type'] ?? 'deck')),
            );
            $entityId = max(0, (int) ($query['entity_id'] ?? 0));
            $allowedEntityTypes = ['deck', 'team', 'event', 'season'];
            if (!in_array($entityType, $allowedEntityTypes, true) || $entityId <= 0) {
                $add(['Error', 'Entidad o identificador invalido']);
            } else {
                $title = 'Informe completo ' . ucfirst($entityType) . ' #' . $entityId;
                $add(['SECCION', 'FICHA']);
                if ($entityType === 'deck') {
                    $entity =
                        array_values(
                            array_filter(
                                $allDecks,
                                static fn(array $row): bool => (int) ($row['id'] ?? 0) ===
                                    $entityId,
                            ),
                        )[0] ?? [];
                    $add(['Deck', $entity['name'] ?? 'No encontrado']);
                    $add(['Equipo', $entity['team'] ?? 'Sin equipo']);
                    $add(['Formato', $entity['format'] ?? ($entity['ruleset'] ?? 'Sin formato')]);
                    $add(['Division', $entity['division'] ?? 'Sin division']);
                    $add(['Arquetipo', $entity['archetype'] ?? '']);
                    $add(['Variante', $entity['variations'] ?? '']);
                } elseif ($entityType === 'team') {
                    $entity =
                        array_values(
                            array_filter(
                                $teams,
                                static fn(array $row): bool => (int) ($row['id'] ?? 0) ===
                                    $entityId,
                            ),
                        )[0] ?? [];
                    $add(['Equipo', $entity['name'] ?? 'No encontrado']);
                    $add(['Codigo', $entity['short_code'] ?? ($entity['code'] ?? '')]);
                    $add(['Ciudad', $entity['city'] ?? '']);
                } elseif ($entityType === 'event') {
                    $entity =
                        array_values(
                            array_filter(
                                $competitions,
                                static fn(array $row): bool => (int) ($row['id'] ?? 0) ===
                                    $entityId,
                            ),
                        )[0] ?? [];
                    $add(['Evento', $entity['name'] ?? 'No encontrado']);
                    $add(['Tipo', $entity['type'] ?? '']);
                    $add(['Formato', $entity['event_ruleset'] ?? '']);
                    $add(['Estado', $entity['status'] ?? '']);
                    $summary =
                        array_values(
                            array_filter(
                                $appData['eventSummaries'] ?? [],
                                static fn(array $row): bool => (int) ($row['competition_id'] ??
                                    0) === $entityId,
                            ),
                        )[0] ?? [];
                    if ($summary) {
                        $add(['Campeon', $summary['champion_name'] ?? '']);
                        $add(['Subcampeon', $summary['runner_up_name'] ?? '']);
                        $add(['Resumen congelado', $summary['finalized_on'] ?? '']);
                        $add(['']);
                        $add(['CLASIFICACION DEFINITIVA']);
                        $add(['Posicion', 'Deck', 'Equipo', 'PTS', 'Partidas G/E/P', 'Duelos G/P']);
                        foreach (
                            json_decode((string) ($summary['standings_json'] ?? '[]'), true) ?: []
                            as $standing
                        ) {
                            $add([
                                $standing['position'] ?? '',
                                $standing['deck_name'] ?? '',
                                $standing['team_name'] ?? '',
                                $standing['points'] ?? 0,
                                ($standing['wins'] ?? 0) .
                                '/' .
                                ($standing['draws'] ?? 0) .
                                '/' .
                                ($standing['losses'] ?? 0),
                                ($standing['duels_won'] ?? 0) .
                                '/' .
                                ($standing['duels_lost'] ?? 0),
                            ]);
                        }
                        $add(['']);
                        $add(['METAGAME DEL EVENTO']);
                        $add(['Deck', 'Apariciones', 'Cuota']);
                        foreach (
                            json_decode((string) ($summary['metagame_json'] ?? '[]'), true) ?: []
                            as $metaRow
                        ) {
                            $add([
                                $metaRow['deck_name'] ?? '',
                                $metaRow['appearances'] ?? 0,
                                ($metaRow['share'] ?? 0) . '%',
                            ]);
                        }
                        $stats =
                            json_decode((string) ($summary['statistics_json'] ?? '{}'), true) ?: [];
                        $add(['']);
                        $add(['MEJORES ESTADISTICAS']);
                        $add(['Partidas', $stats['matches'] ?? 0]);
                        if (!empty($stats['fastest_victory'])) {
                            $add([
                                'Victoria mas rapida',
                                ($stats['fastest_victory']['deck'] ?? '') .
                                ' | ' .
                                ($stats['fastest_victory']['value'] ?? 0) .
                                ' s',
                            ]);
                        }
                        if (!empty($stats['fewest_turns_victory'])) {
                            $add([
                                'Victoria en menos turnos',
                                ($stats['fewest_turns_victory']['deck'] ?? '') .
                                ' | ' .
                                ($stats['fewest_turns_victory']['value'] ?? 0) .
                                ' turnos',
                            ]);
                        }
                    }
                } else {
                    $entity =
                        array_values(
                            array_filter(
                                $appData['seasons'] ?? [],
                                static fn(array $row): bool => (int) ($row['id'] ?? 0) ===
                                    $entityId,
                            ),
                        )[0] ?? [];
                    $add(['Temporada', $entity['name'] ?? 'No encontrada']);
                    $add(['Inicio', $entity['starts_on'] ?? '']);
                    $add(['Fin', $entity['ends_on'] ?? '']);
                    $add(['Estado', $entity['status'] ?? '']);
                }
                $relatedDeckIds = [];
                if ($entityType === 'deck') {
                    $relatedDeckIds = [$entityId];
                }
                if ($entityType === 'team') {
                    $relatedDeckIds = array_map(
                        static fn(array $row): int => (int) $row['id'],
                        array_filter(
                            $allDecks,
                            static fn(array $row): bool => (int) ($row['team_id'] ?? 0) ===
                                $entityId,
                        ),
                    );
                }
                $reportStats = [
                    'matches' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'duels_won' => 0,
                    'duels_drawn' => 0,
                    'duels_lost' => 0,
                ];
                $reportMatchups = [];
                $reportSnapshotOrigins = [];
                $add(['']);
                $add(['SECCION', 'PARTIDAS E HISTORIAL']);
                $add([
                    'Fecha',
                    'Evento',
                    'Deck A',
                    'Deck B',
                    'Resultado',
                    'Marcador',
                    'Duelos',
                    'Procedencia snapshot',
                ]);
                foreach ($fixtures as $rounds) {
                    foreach ($rounds as $round) {
                        foreach ($round['matches'] ?? [] as $match) {
                            $competition =
                                array_values(
                                    array_filter(
                                        $competitions,
                                        static fn(array $row): bool => (int) ($row['id'] ?? 0) ===
                                            (int) ($match['competition_id'] ?? 0),
                                    ),
                                )[0] ?? [];
                            $belongs =
                                $entityType === 'event'
                                    ? (int) ($match['competition_id'] ?? 0) === $entityId
                                    : ($entityType === 'season'
                                        ? (int) ($competition['season_id'] ?? 0) === $entityId
                                        : in_array(
                                                (int) ($match['deck_a_id'] ?? 0),
                                                $relatedDeckIds,
                                                true,
                                            ) ||
                                            in_array(
                                                (int) ($match['deck_b_id'] ?? 0),
                                                $relatedDeckIds,
                                                true,
                                            ));
                            if (!$belongs) {
                                continue;
                            }
                            $origins = array_values(
                                array_unique(
                                    array_map(
                                        static fn(array $row): string => (string) ($row[
                                            'snapshot_status'
                                        ] ??
                                            ($row['data_origin'] ?? 'unknown')),
                                        array_filter(
                                            $appData['matchSnapshots'] ?? [],
                                            static fn(array $row): bool => (int) ($row[
                                                'match_id'
                                            ] ?? 0) === (int) ($match['id'] ?? 0),
                                        ),
                                    ),
                                ),
                            );
                            foreach ($origins as $origin) {
                                $reportSnapshotOrigins[$origin] =
                                    ($reportSnapshotOrigins[$origin] ?? 0) + 1;
                            }
                            if (
                                $relatedDeckIds &&
                                in_array(
                                    (string) ($match['estado'] ?? ($match['status'] ?? '')),
                                    ['FINALIZADA', 'completed', 'finalizada'],
                                    true,
                                ) &&
                                in_array((string) ($match['result'] ?? ''), ['A', 'B', 'E'], true)
                            ) {
                                $onA = in_array(
                                    (int) ($match['deck_a_id'] ?? 0),
                                    $relatedDeckIds,
                                    true,
                                );
                                $onB = in_array(
                                    (int) ($match['deck_b_id'] ?? 0),
                                    $relatedDeckIds,
                                    true,
                                );
                                $side = $onA ? 'A' : ($onB ? 'B' : '');
                                $rivalName = $onA
                                    ? (string) ($match['b'] ?? '')
                                    : (string) ($match['a'] ?? '');
                                if ($side !== '') {
                                    $reportStats['matches']++;
                                    $resultCode = (string) ($match['result'] ?? '');
                                    if ($resultCode === $side) {
                                        $reportStats['wins']++;
                                    } elseif ($resultCode === 'E') {
                                        $reportStats['draws']++;
                                    } else {
                                        $reportStats['losses']++;
                                    }
                                    if (!isset($reportMatchups[$rivalName])) {
                                        $reportMatchups[$rivalName] = [
                                            'wins' => 0,
                                            'draws' => 0,
                                            'losses' => 0,
                                        ];
                                    }
                                    if ($resultCode === $side) {
                                        $reportMatchups[$rivalName]['wins']++;
                                    } elseif ($resultCode === 'E') {
                                        $reportMatchups[$rivalName]['draws']++;
                                    } else {
                                        $reportMatchups[$rivalName]['losses']++;
                                    }
                                    foreach (['d1', 'd2', 'd3', 'd4', 'd5'] as $duelKey) {
                                        $duelResult = (string) ($match[$duelKey] ?? '');
                                        if ($duelResult === '') {
                                            continue;
                                        }
                                        if ($duelResult === $side) {
                                            $reportStats['duels_won']++;
                                        } elseif ($duelResult === 'E') {
                                            $reportStats['duels_drawn']++;
                                        } else {
                                            $reportStats['duels_lost']++;
                                        }
                                    }
                                }
                            }
                            $add([
                                $match['scheduled_on'] ?? '',
                                $competition['name'] ?? ($match['competition_name'] ?? ''),
                                $match['a'] ?? '',
                                $match['b'] ?? '',
                                $match['result'] ?? '',
                                $match['score'] ?? '',
                                implode(
                                    '/',
                                    array_filter([
                                        $match['d1'] ?? '',
                                        $match['d2'] ?? '',
                                        $match['d3'] ?? '',
                                        $match['d4'] ?? '',
                                        $match['d5'] ?? '',
                                    ]),
                                ),
                                implode(', ', $origins) ?: 'sin snapshot',
                            ]);
                        }
                    }
                }
                if ($relatedDeckIds) {
                    $add(['']);
                    $add(['SECCION', 'ESTADISTICAS']);
                    $add([
                        'Partidas G/E/P',
                        $reportStats['wins'] .
                        '/' .
                        $reportStats['draws'] .
                        '/' .
                        $reportStats['losses'],
                    ]);
                    $add([
                        'Duelos G/E/P',
                        $reportStats['duels_won'] .
                        '/' .
                        $reportStats['duels_drawn'] .
                        '/' .
                        $reportStats['duels_lost'],
                    ]);
                    $add([
                        'Winrate partidas',
                        $reportStats['matches']
                            ? round(($reportStats['wins'] / $reportStats['matches']) * 100, 2) . '%'
                            : 'N/D',
                    ]);
                    $add([
                        'Cobertura snapshots',
                        implode(
                            ', ',
                            array_map(
                                static fn($origin, $count): string => $origin . ': ' . $count,
                                array_keys($reportSnapshotOrigins),
                                array_values($reportSnapshotOrigins),
                            ),
                        ) ?:
                        'Sin snapshots',
                    ]);
                    $add(['']);
                    $add(['SECCION', 'MATCHUPS']);
                    $add(['Rival', 'Partidas G/E/P']);
                    uasort(
                        $reportMatchups,
                        static fn(array $a, array $b): int => array_sum($b) <=> array_sum($a),
                    );
                    foreach ($reportMatchups as $rival => $record) {
                        $add([
                            $rival,
                            $record['wins'] . '/' . $record['draws'] . '/' . $record['losses'],
                        ]);
                    }
                    $add(['']);
                    $add(['SECCION', 'MEDALLAS']);
                    $add(['Deck ID', 'Medalla', 'Rareza', 'Fecha', 'Evento', 'Rival']);
                    foreach ($appData['medalUnlocks'] ?? [] as $medal) {
                        if (
                            $medal['entity_type'] === 'deck' &&
                            in_array((int) $medal['entity_id'], $relatedDeckIds, true)
                        ) {
                            $add([
                                $medal['entity_id'],
                                $medal['medal_name'],
                                $medal['rarity'],
                                $medal['unlocked_at'],
                                $medal['competition_id'],
                                $medal['rival_name'],
                            ]);
                        }
                    }
                    $add(['']);
                    $add(['SECCION', 'CARTAS Y MULTIMEDIA']);
                    $add(['Deck ID', 'Tipo', 'Nombre / URL', 'Seccion', 'Cantidad']);
                    foreach ($cards as $card) {
                        if (in_array((int) $card['deck_id'], $relatedDeckIds, true)) {
                            $add([
                                $card['deck_id'],
                                'Carta',
                                $card['card_name'],
                                $card['section'],
                                $card['quantity'],
                            ]);
                        }
                    }
                    foreach ($media as $item) {
                        if (in_array((int) $item['deck_id'], $relatedDeckIds, true)) {
                            $add([
                                $item['deck_id'],
                                'Media',
                                $item['url'],
                                $item['media_type'],
                                '',
                            ]);
                        }
                    }
                    if (
                        !array_filter(
                            $cards,
                            static fn(array $card): bool => in_array(
                                (int) $card['deck_id'],
                                $relatedDeckIds,
                                true,
                            ),
                        )
                    ) {
                        $add([
                            '',
                            'Aviso',
                            'Sin lista exacta de cartas cargada; no se infiere contenido.',
                            '',
                            '',
                        ]);
                    }
                }
            }
        }

        return ['title' => $title, 'rows' => $rows];
    }
}
