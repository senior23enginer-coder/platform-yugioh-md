<?php
declare(strict_types=1);
require __DIR__ . '/../includes/decks/template-import.php';
$sample = "# STATIONX-DECK-TEMPLATE/1\n[DECK]\nnombre=Deck sintético\narquetipo=Prueba\n[MAIN]\n3;Carta Alfa\n[EXTRA]\n1;Carta Beta\n[SIDE]\n2;Carta Gamma\n[RESERVA]\n1;Carta Delta\n";
$parsed = ygo_parse_deck_template($sample);
assert($parsed['name'] === 'Deck sintético');
assert(count($parsed['cards']) === 4);
assert($parsed['cards'][3]['section'] === 'tech');
foreach ([str_replace('3;Carta Alfa', '0;Carta Alfa', $sample),
    str_replace('[MAIN]', '[UNKNOWN]', $sample),
    str_replace('nombre=Deck sintético', 'nombre=', $sample)] as $bad) {
    try { ygo_parse_deck_template($bad); throw new RuntimeException('Debe rechazarse'); }
    catch (InvalidArgumentException $expected) { /* fail closed */ }
}
if (extension_loaded('pdo_sqlite')) {
    $db = new PDO('sqlite::memory:');
    $db->exec('CREATE TABLE sx_ygo_decks (id INTEGER PRIMARY KEY, division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active)');
    $db->exec('CREATE TABLE sx_ygo_deck_cards (deck_id, card_name, role, quantity, section, notes)');
    // Parser-only test: upload handler requires a real HTTP-uploaded file.
}
echo json_encode(['ok' => true, 'cards' => count($parsed['cards']), 'invalidRejected' => 3]) . "\n";
