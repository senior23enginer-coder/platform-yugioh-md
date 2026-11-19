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
            if (!preg_match('/^([1-3]);([^;\r\n]{1,160})$/u', $line, $match)) {
                throw new InvalidArgumentException('Carta invalida en linea ' . ($number + 1) . '. Usa cantidad;nombre.');
            }
            $name = trim($match[2]);
            if ($name === '') throw new InvalidArgumentException('Nombre de carta vacio.');
            $cards[] = ['section' => $sections[$section], 'quantity' => (int) $match[1], 'name' => $name];
        } else {
            throw new InvalidArgumentException('Contenido fuera de una seccion en linea ' . ($number + 1));
        }
    }
    if (!$header || empty($metadata['nombre']) || mb_strlen($metadata['nombre']) > 160 || count($cards) < 1 || count($cards) > 150) {
        throw new InvalidArgumentException('La plantilla requiere nombre y entre 1 y 150 lineas de cartas.');
    }
    foreach (['arquetipo', 'estilo', 'notas'] as $key) {
        if (mb_strlen($metadata[$key] ?? '') > ($key === 'notas' ? 2000 : 120)) throw new InvalidArgumentException('Campo demasiado largo: ' . $key);
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
        // Only TXT-created decks are eligible for one-click GPT repository bootstrap.
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['deck', $id, 'import_deck_template', json_encode(['origin' => 'stationx_txt', 'name' => $data['name']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $pdo->commit();
        return 'deck_' . $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}


/** Build the exact native package from the current database inventory (no invented IDs). */
function ygo_gpt_sync_files(array $inventory, string $app): array
{
    require_once __DIR__ . '/data-http.php';
    $files = ygo_data_export_files($app, $inventory, null, [], []);
    $folder = ygo_data_folder($files, (int)$inventory['deck']['id'], (string)$inventory['deck']['name']);
    $source = [
        'schema' => 'station-x-inventory-source/1',
        'deck_id' => (int)$inventory['deck']['id'],
        'deck_name' => (string)$inventory['deck']['name'],
        'format' => (string)($inventory['format'] ?? 'tcg'),
        'inventory_sha256' => (string)$inventory['inventory_sha256'],
        'cards' => $inventory['cards'],
        'origin' => 'stationx_txt',
        'instructions' => 'Ejecuta manualmente los prompts A01–A10. No alterar el inventario ni publicar automáticamente.',
    ];
    $files[$folder . '/datos/stationx-origen.json'] = ygo_data_json($source);
    return $files;
}

/** Fixed-host GitHub Contents client, injectable for isolated regression tests. */
function ygo_gpt_github_request(string $method, string $path, ?array $payload = null): array
{
    $token = trim((string)(getenv('STATIONX_GITHUB_TOKEN') ?: ''));
    if ($token === '' || !function_exists('curl_init')) {
        throw new InvalidArgumentException('Configura STATIONX_GITHUB_TOKEN en el servidor y habilita PHP cURL antes de sincronizar.');
    }
    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') throw new InvalidArgumentException('Ruta GitHub inválida.');
    }
    $url = 'https://api.github.com/repos/senior23enginer-coder/gpt-yugioh-promps-platform/contents/'
        . implode('/', array_map('rawurlencode', $segments))
        . ($method === 'GET' ? '?ref=base' : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $token,
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: StationX-deck-sync',
            'Content-Type: application/json',
        ],
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $failure = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new InvalidArgumentException('No se pudo conectar con GitHub: ' . $failure);
    $data = json_decode($raw, true);
    return ['status' => $status, 'body' => is_array($data) ? $data : []];
}

/** Create only absent content; update ONLY the Station X inventory source with SHA check. */
function ygo_gpt_sync_upload(array $files, ?callable $request = null): array
{
    $request ??= 'ygo_gpt_github_request';
    $folder = ygo_data_folder($files, 0, 'deck');
    $manifest = $folder . '/datos/stationx-origen.json';
    $created = 0;
    $updated = 0;
    $unchanged = 0;
    // Fail closed on a pre-existing package belonging to another deck or inventory.
    $canonical = $folder . '/datos/stationx-deck.json';
    $initial = json_decode($files[$canonical] ?? 'null', true);
    if (!is_array($initial)) throw new InvalidArgumentException('El paquete Station X inicial está incompleto.');
    $existingPackage = $request('GET', '01_INFORMACION_PARA_IMPORTAR/decks/' . $canonical, null);
    if (($existingPackage['status'] ?? 0) === 200) {
        $encoded = preg_replace('/\s+/', '', (string)($existingPackage['body']['content'] ?? ''));
        $decoded = base64_decode($encoded, true);
        $remote = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($remote) || (int)($remote['deck_id'] ?? 0) !== (int)$initial['deck_id']) {
            throw new InvalidArgumentException('Ya existe una carpeta GPT de otro deck; no se modificó.');
        }
        if (($remote['inventory_sha256'] ?? '') !== ($initial['inventory_sha256'] ?? '')) {
            throw new InvalidArgumentException('La lista en GPT es diferente. No se sobrescribieron análisis; concilia inventario.');
        }
    } elseif (($existingPackage['status'] ?? 0) !== 404) {
        throw new InvalidArgumentException('No fue posible comprobar la identidad del paquete GPT existente.');
    }
    // Ensure the canonical content package and Markdown are created BEFORE the source marker.
    $paths = array_keys($files);
    usort($paths, static fn(string $a, string $b): int => ($a === $manifest ? 1 : 0) <=> ($b === $manifest ? 1 : 0));
    foreach ($paths as $path) {
        $lookup = $request('GET', '01_INFORMACION_PARA_IMPORTAR/decks/' . $path, null);
        $existing = $lookup['body'] ?? [];
        if (($lookup['status'] ?? 0) !== 404 && ($lookup['status'] ?? 0) !== 200) {
            throw new InvalidArgumentException('No se pudo comprobar el archivo remoto: ' . $path);
        }
        if (($lookup['status'] ?? 0) === 200 && $path !== $manifest) {
            // Never overwrite authored A01–A10, images, guides or the curated JSON.
            $unchanged++;
            continue;
        }
        if (($lookup['status'] ?? 0) === 200 && $path === $manifest) {
            $raw = base64_decode(preg_replace('/\s+/', '', (string)($existing['content'] ?? '')), true);
            $old = $raw === false ? null : json_decode($raw, true);
            $new = json_decode($files[$path], true);
            if (!is_array($old) || (int)($old['deck_id'] ?? 0) !== (int)($new['deck_id'] ?? -1)) {
                throw new InvalidArgumentException('El directorio GPT contiene un origen incompatible. No se sobrescribió.');
            }
            if (($old['inventory_sha256'] ?? '') !== ($new['inventory_sha256'] ?? '')) {
                throw new InvalidArgumentException('Inventario modificado: exporta y concilia manualmente antes de actualizar GPT.');
            }
            if ($old === $new) { $unchanged++; continue; }
        }
        $payload = [
            'message' => ($lookup['status'] === 404 ? 'chore(decks): bootstrap TXT deck from Station X' : 'chore(decks): synchronize TXT deck source'),
            'content' => base64_encode($files[$path]),
            'branch' => 'base',
        ];
        if ($lookup['status'] === 200) {
            if (!is_string($existing['sha'] ?? null)) throw new InvalidArgumentException('GitHub no devolvió SHA de control.');
            $payload['sha'] = $existing['sha'];
        }
        $write = $request('PUT', '01_INFORMACION_PARA_IMPORTAR/decks/' . $path, $payload);
        if (!in_array((int)($write['status'] ?? 0), [200, 201], true)) {
            throw new InvalidArgumentException('GitHub rechazó el archivo ' . $path . '. Comprueba permisos Contents:write y conflictos de versión.');
        }
        if ($lookup['status'] === 404) $created++; else $updated++;
    }
    return ['folder' => $folder, 'created' => $created, 'updated' => $updated, 'unchanged' => $unchanged];
}

/** Explicit, permission-checked and CSRF-protected operation; never runs prompts. */
function ygo_gpt_sync_txt_deck(PDO $pdo, int $deckId, string $csrf): string
{
    if ($deckId < 1) throw new InvalidArgumentException('Selecciona un deck creado desde TXT.');
    if (!isset($_SESSION['sx_csrf']) || !is_string($_SESSION['sx_csrf'])
        || !hash_equals($_SESSION['sx_csrf'], $csrf) || $csrf === '') {
        throw new InvalidArgumentException('Sesión caducada. Recarga Station X e inténtalo otra vez.');
    }
    $audit = $pdo->prepare("SELECT id FROM sx_ygo_audit_log WHERE entity_type='deck' AND entity_id=? AND action='import_deck_template' LIMIT 1");
    $audit->execute([$deckId]);
    if (!$audit->fetchColumn()) throw new InvalidArgumentException('Solo los decks creados desde plantilla TXT pueden utilizar este envío inicial.');
    $inventory = ygo_combo_inventory($pdo, $deckId, false, false);
    $files = ygo_gpt_sync_files($inventory, dirname(__DIR__, 2));
    $result = ygo_gpt_sync_upload($files);
    return 'gpt_sync_' . $deckId . '_' . $result['created'] . '_' . $result['unchanged'];
}
