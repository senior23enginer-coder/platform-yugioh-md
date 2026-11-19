<?php
declare(strict_types=1);
require __DIR__ . '/../includes/decks/template-import.php';
$sample = "# STATIONX-DECK-TEMPLATE/1\n[DECK]\nnombre=Deck sintético\narquetipo=Prueba\n[MAIN]\n3;Carta Alfa\n[EXTRA]\n1;Carta Beta\n[SIDE]\n2;Carta Gamma\n[RESERVA]\n1;Carta Delta\n";
$parsed = ygo_parse_deck_template($sample);
if ($parsed['name'] !== 'Deck sintético') throw new RuntimeException('Nombre incorrecto');
if (count($parsed['cards']) !== 4) throw new RuntimeException('Conteo incorrecto');
if ($parsed['cards'][3]['section'] !== 'tech') throw new RuntimeException('Reserva incorrecta');
foreach ([str_replace('3;Carta Alfa', '0;Carta Alfa', $sample),
    str_replace('[MAIN]', '[UNKNOWN]', $sample),
    str_replace('nombre=Deck sintético', 'nombre=', $sample),
    str_replace('nombre=Deck sintético', 'nombre=' . str_repeat('N', 161), $sample),
    str_replace('arquetipo=Prueba', 'arquetipo=' . str_repeat('A', 121), $sample),
    str_replace('arquetipo=Prueba', "arquetipo=Prueba\nestilo=" . str_repeat('E', 121), $sample),
    str_replace('3;Carta Alfa', '3;' . str_repeat('C', 161), $sample)] as $bad) {
    try { ygo_parse_deck_template($bad); throw new RuntimeException('Debe rechazarse'); }
    catch (InvalidArgumentException $expected) { /* fail closed */ }
}
// Boundary values must be accepted without risking database truncation.
$validBoundary = str_replace('nombre=Deck sintético', 'nombre=' . str_repeat('N', 160), $sample);
$validBoundary = str_replace('arquetipo=Prueba', 'arquetipo=' . str_repeat('A', 120) . "\nestilo=" . str_repeat('E', 120), $validBoundary);
$validBoundary = str_replace('3;Carta Alfa', '3;' . str_repeat('C', 160), $validBoundary);
if (count(ygo_parse_deck_template($validBoundary)['cards']) !== 4) throw new RuntimeException('Límites válidos rechazados');
if (extension_loaded('pdo_sqlite')) {
    $db = new PDO('sqlite::memory:');
    $db->exec('CREATE TABLE sx_ygo_decks (id INTEGER PRIMARY KEY, division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active)');
    $db->exec('CREATE TABLE sx_ygo_deck_cards (deck_id, card_name, role, quantity, section, notes)');
    // Parser-only test: upload handler requires a real HTTP-uploaded file.
}
// GitHub sync is fully mocked: no remote writes, no real decks changed.
$pilotInventory = ['deck' => ['id' => 900001, 'name' => 'Prueba Nueva TXT'], 'format' => 'tcg',
    'inventory_sha256' => str_repeat('a', 64), 'cards' => [
        ['id' => 101, 'card_name' => 'Carta Alfa', 'section' => 'main', 'quantity' => 3],
        ['id' => 102, 'card_name' => 'Carta Beta', 'section' => 'extra', 'quantity' => 1],
    ]];
$files = ygo_gpt_sync_files($pilotInventory, dirname(__DIR__));
$remote = [];
$requests = 0;
$mock = static function (string $method, string $path, ?array $payload) use (&$remote, &$requests): array {
    $requests++;
    if ($method === 'GET') {
        if (!isset($remote[$path])) return ['status' => 404, 'body' => []];
        return ['status' => 200, 'body' => [
            'sha' => $remote[$path]['sha'],
            'content' => base64_encode($remote[$path]['bytes']),
        ]];
    }
    if ($method !== 'PUT') throw new RuntimeException('Unexpected method');
    if (isset($remote[$path]) && ($payload['sha'] ?? '') !== $remote[$path]['sha']) return ['status' => 409, 'body' => []];
    if (!isset($remote[$path]) && isset($payload['sha'])) return ['status' => 409, 'body' => []];
    $bytes = base64_decode((string)$payload['content'], true);
    $created = !isset($remote[$path]);
    $remote[$path] = ['sha' => hash('sha1', $bytes), 'bytes' => $bytes];
    return ['status' => $created ? 201 : 200, 'body' => []];
};
$first = ygo_gpt_sync_upload($files, $mock);
if ($first['created'] !== 3 || $first['updated'] !== 0) throw new RuntimeException('Initial GPT bootstrap incomplete');
$second = ygo_gpt_sync_upload($files, $mock);
if ($second['created'] !== 0 || $second['updated'] !== 0 || $second['unchanged'] !== 3) throw new RuntimeException('GPT sync is not idempotent');
$root = '01_INFORMACION_PARA_IMPORTAR/decks/' . $first['folder'];
$packagePath = $root . '/datos/stationx-deck.json';
$originalPackage = $remote[$packagePath]['bytes'];
$changed = $files;
$sourcePath = $first['folder'] . '/datos/stationx-origen.json';
$sourceJson = json_decode($changed[$sourcePath], true);
$sourceJson['instructions'] .= ' Actualizado.';
$changed[$sourcePath] = json_encode($sourceJson, JSON_UNESCAPED_UNICODE);
$again = ygo_gpt_sync_upload($changed, $mock);
if ($again['updated'] !== 1 || $again['created'] !== 0 || $remote[$packagePath]['bytes'] !== $originalPackage) {
    throw new RuntimeException('Synchronization overwrote authored package');
}
$drift = $files;
$driftJson = json_decode($drift[$sourcePath], true);
$driftJson['inventory_sha256'] = str_repeat('b', 64);
$drift[$sourcePath] = json_encode($driftJson);
try { ygo_gpt_sync_upload($drift, $mock); throw new RuntimeException('Inventory drift accepted'); }
catch (InvalidArgumentException $expected) {}
if ($remote[$packagePath]['bytes'] !== $originalPackage) throw new RuntimeException('Protected deck content changed');
echo json_encode(['ok' => true, 'cards' => count($parsed['cards']), 'invalidRejected' => 7]) . "\n";
