<?php
declare(strict_types=1);

/** Strict, UTF-8 deck template. No prompt prose or executable rules accepted. */
function ygo_parse_deck_template(string $text): array
{
    if (strlen($text) > 131072 || str_contains($text, "\0") || !preg_match('//u', $text)) {
        throw new InvalidArgumentException('TXT demasiado grande o sin codificacion UTF-8 valida.');
    }
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $lines = preg_split('/\R/u', $text) ?: [];
    $header = false;
    $section = '';
    $metadata = [];
    $cards = [];
    $sections = ['MAIN' => 'main', 'EXTRA' => 'extra', 'SIDE' => 'side', 'RESERVA' => 'tech'];
    foreach ($lines as $number => $raw) {
        $line = trim($raw);
        if ($line === '' || str_starts_with($line, '#') && $header) continue;
        if (!$header) {
            if ($line !== '# STATIONX-DECK-TEMPLATE/1') throw new InvalidArgumentException('Cabecera de plantilla no valida.');
            $header = true;
            continue;
        }
        if (preg_match('/^\[([A-Z]+)\]$/', $line, $match)) {
            $section = $match[1];
            if ($section !== 'DECK' && !isset($sections[$section])) throw new InvalidArgumentException('Seccion desconocida: ' . $section);
            continue;
        }
        if ($section === 'DECK') {
            if (!preg_match('/^([a-z_]+)=(.*)$/u', $line, $match) || !in_array($match[1], ['nombre','arquetipo','estilo','notas'], true) || isset($metadata[$match[1]])) {
                throw new InvalidArgumentException('Campo de deck invalido en linea ' . ($number + 1));
            }
            $metadata[$match[1]] = trim($match[2]);
        } elseif (isset($sections[$section])) {
            if (!preg_match('/^([1-3]);([^;\r\n]{1,180})$/u', $line, $match)) {
                throw new InvalidArgumentException('Carta invalida en linea ' . ($number + 1) . '. Usa cantidad;nombre.');
            }
            $name = trim($match[2]);
            if ($name === '') throw new InvalidArgumentException('Nombre de carta vacio.');
            $cards[] = ['section' => $sections[$section], 'quantity' => (int) $match[1], 'name' => $name];
        } else {
            throw new InvalidArgumentException('Contenido fuera de una seccion en linea ' . ($number + 1));
        }
    }
    if (!$header || empty($metadata['nombre']) || mb_strlen($metadata['nombre']) > 180 || count($cards) < 1 || count($cards) > 150) {
        throw new InvalidArgumentException('La plantilla requiere nombre y entre 1 y 150 lineas de cartas.');
    }
    foreach (['arquetipo', 'estilo', 'notas'] as $key) {
        if (mb_strlen($metadata[$key] ?? '') > ($key === 'notas' ? 2000 : 180)) throw new InvalidArgumentException('Campo demasiado largo: ' . $key);
    }
    return ['name' => $metadata['nombre'], 'archetype' => $metadata['arquetipo'] ?? '',
        'style' => $metadata['estilo'] ?? '', 'notes' => $metadata['notas'] ?? '', 'cards' => $cards];
}

/** Creates a native deck and its cards together; failures leave neither behind. */
function ygo_import_deck_template(PDO $pdo, array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        (int) ($file['size'] ?? 0) < 1 || (int) ($file['size'] ?? 0) > 131072 ||
        !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new InvalidArgumentException('Selecciona un archivo TXT valido de hasta 128 KiB.');
    }
    $filename = (string) ($file['name'] ?? '');
    if (!preg_match('/\.txt$/i', $filename)) throw new InvalidArgumentException('Solo se aceptan archivos .txt.');
    $source = file_get_contents((string) $file['tmp_name']);
    if ($source === false) throw new InvalidArgumentException('No se pudo leer el TXT.');
    $data = ygo_parse_deck_template($source);
    $exists = $pdo->prepare('SELECT id FROM sx_ygo_decks WHERE name=? AND active=1 LIMIT 1');
    $exists->execute([$data['name']]);
    if ($exists->fetchColumn()) throw new InvalidArgumentException('Ya existe un deck activo con ese nombre.');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active) VALUES (NULL, NULL, 999, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
            ->execute([$data['name'], $data['archetype'], $data['style'], '', '', '', $data['notes'], '']);
        $id = (int) $pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO sx_ygo_deck_cards (deck_id, card_name, role, quantity, section, notes) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($data['cards'] as $card) $insert->execute([$id, $card['name'], '', $card['quantity'], $card['section'], '']);
        $pdo->commit();
        return 'deck_' . $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
