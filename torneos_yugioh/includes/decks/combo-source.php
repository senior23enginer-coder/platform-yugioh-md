<?php
declare(strict_types=1);

function ygo_combo_source_id(int $deckId, string $route): string
{
    $a = 2166136261;
    $b = 5381;
    foreach (mb_str_split($deckId . ':' . $route) as $char) {
        $code = unpack('v', mb_convert_encoding($char, 'UTF-16LE', 'UTF-8'))[1];
        $a = (($a ^ $code) * 16777619) & 0xffffffff;
        $b = (($b * 33) ^ $code) & 0xffffffff;
    }
    return sprintf('documented-%d-%08x%08x', $deckId, $a, $b);
}

function ygo_combo_source_routes(int $deckId): array
{
    static $cache = [];
    if (isset($cache[$deckId])) {
        return $cache[$deckId];
    }
    $app = dirname(__DIR__, 2);
    $root = realpath($app . '/assets/editorial');
    $result = [];
    $state = json_decode(
        (string) @file_get_contents($app . '/assets/editorial/strategy-content-state.json'),
        true,
    );
    $entry = $state['checked_studies'][(string) $deckId] ?? null;
    if (!$entry || !is_string($entry['path'] ?? null)) {
        return $cache[$deckId] = [];
    }
    $file = realpath($app . '/' . ltrim($entry['path'], './'));
    if (
        !$file ||
        !$root ||
        !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/')
    ) {
        return $cache[$deckId] = [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    if ((int) ($data['deck_id'] ?? 0) !== $deckId) {
        return $cache[$deckId] = [];
    }
    foreach ($data['routes'] ?? [] as $r) {
        if (is_string($r['id'] ?? null)) {
            $result[ygo_combo_source_id($deckId, $r['id'])] = $r['id'];
        }
    }
    return $cache[$deckId] = $result;
}

function ygo_combo_source_reference(array $input, int $deckId): array
{
    $route = ygo_combo_source_routes($deckId)[$input['id'] ?? ''] ?? null;
    if (isset($input['source_route_id']) && $input['source_route_id'] !== $route) {
        throw new InvalidArgumentException('El combo no corresponde a esa ruta documental.');
    }
    return $route ? ['source_route_id' => $route] : [];
}

function ygo_combo_source_markdown(array $record): string
{
    $doc = $record['document'];
    $cards = array_column($record['cards_snapshot'], null, 'id');
    $kind = [
        'opening' => 'Estado inicial',
        'step' => 'Paso',
        'decision' => 'Decisión',
        'result' => 'Resultado',
    ];
    $out = [
        '# ' . $doc['title'],
        '',
        '- Deck ID: `' . $doc['deck_id'] . '`',
        '- Ruta de origen: `' . $doc['source_route_id'] . '`',
        '- Revisión del editor: **' . $record['revision'] . '**',
        '- Estado documental: `' . ($doc['editorial_status'] ?? 'draft') . '`',
        '',
        '## Objetivo',
        $doc['objective'],
        '',
        '## Requisitos y límites',
        $doc['conditions'],
        '',
        '## Nodos editables',
    ];
    foreach ($doc['nodes'] as $i => $node) {
        array_push(
            $out,
            '',
            '### ' . ($i + 1) . '. ' . $node['title'] . ' · ' . $kind[$node['kind']],
            '',
            $node['action'],
            '',
            $node['explanation'],
            '',
        );
        foreach ($node['cards'] as $id) {
            $out[] =
                '- Carta: ' .
                ($cards[$id]['card_name'] ?? 'Identidad no disponible') .
                ' · ID nativo `' .
                $id .
                '` · ' .
                ($cards[$id]['section'] ?? '');
        }
        foreach ($node['details'] ?? [] as $key => $value) {
            if ($value !== '') {
                $out[] = '- ' . $key . ': ' . $value;
            }
        }
        foreach ($node['placements'] ?? [] as $p) {
            $id = $node['cards'][$p['card_index']];
            $out[] =
                '- Ubicación: ' .
                ($cards[$id]['card_name'] ?? $id) .
                ' · ' .
                $p['zone'] .
                ' · ' .
                $p['position'] .
                ($p['materials'] !== '' ? ' · Materiales: ' . $p['materials'] : '');
        }
    }
    array_push($out, '', '## Conexiones y ramas');
    $nodes = array_column($doc['nodes'], null, 'id');
    foreach ($doc['edges'] as $edge) {
        $out[] =
            '- ' .
            $nodes[$edge['from']]['title'] .
            ' → ' .
            $nodes[$edge['to']]['title'] .
            ' · ' .
            $edge['label'];
    }
    if (isset($doc['skill_snapshot'])) {
        array_push(
            $out,
            '',
            '## Skill externa',
            json_encode(
                $doc['skill_snapshot'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
        );
    }
    array_push(
        $out,
        '',
        '## Documento nativo de esta revisión',
        '```json',
        json_encode(
            $doc,
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR,
        ),
        '```',
        '',
    );
    return implode("\n", $out);
}

function ygo_combo_source_feed(PDO $pdo): never
{
    $meta = $pdo
        ->query(
            "SELECT id,deck_id,revision,updated_at FROM sx_ygo_created_combos WHERE id LIKE 'documented-%' ORDER BY id",
        )
        ->fetchAll(PDO::FETCH_ASSOC);
    $etag = '"' . hash('sha256', json_encode($meta, JSON_THROW_ON_ERROR)) . '"';
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        header('ETag: ' . $etag);
        http_response_code(304);
        exit();
    }
    $records = [];
    $hashRows = [];
    foreach (
        $pdo
            ->query("SELECT * FROM sx_ygo_created_combos WHERE id LIKE 'documented-%' ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $hashRows[] = [
            'id' => $row['id'],
            'deck_id' => $row['deck_id'],
            'revision' => $row['revision'],
            'updated_at' => $row['updated_at'],
        ];
        $route = ygo_combo_source_routes((int) $row['deck_id'])[$row['id']] ?? null;
        if (!$route) {
            continue;
        }
        $record = ygo_combo_unpack($row, $row['inventory_sha256']);
        $record['document']['source_route_id'] = $route;
        $records[] = [
            'deck_id' => (int) $row['deck_id'],
            'id' => $row['id'],
            'route_id' => $route,
            'revision' => (int) $row['revision'],
            'updated_at' => $row['updated_at'],
            'document' => $record['document'],
            'cards_snapshot' => $record['cards_snapshot'],
            'markdown' => ygo_combo_source_markdown($record),
        ];
    }
    header('ETag: "' . hash('sha256', json_encode($hashRows, JSON_THROW_ON_ERROR)) . '"');
    echo json_encode(
        ['ok' => true, 'schema' => 'station-x-combo-source/1', 'records' => $records],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    exit();
}
