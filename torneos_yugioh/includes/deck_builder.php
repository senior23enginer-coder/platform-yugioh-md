<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use StationX\Yugioh\Infrastructure\Composition\CardCatalogModule;
use StationX\Yugioh\Infrastructure\Cache\CardCatalog\{
    FilesystemCatalogCache,
    FilesystemCatalogImages,
};
use StationX\Yugioh\Infrastructure\Http\CardCatalog\CatalogController;
use StationX\Yugioh\Application\CardCatalog\{CardSummary, InspectCachedCard};

/** Compatibility entry points. Catalog rules, storage, transport and HTTP are independent. */
function ygo_card_cache_dir(): string
{
    return CardCatalogModule::cache()->directory();
}
function ygo_card_download(string $url, int $max = 4000000): string
{
    return CardCatalogModule::provider()->download($url, $max);
}
function ygo_card_cache_write(string $file, string $body): void
{
    FilesystemCatalogCache::writeFile($file, $body);
}
function ygo_card_lookup(int $id): array
{
    return CardCatalogModule::create()->lookup->execute($id);
}
function ygo_card_summary(array $card): array
{
    return CardSummary::fromProvider($card);
}
function ygo_card_inspector_cached(int $id, string $dir): ?array
{
    $cache = CardCatalogModule::cache($dir);
    return (new InspectCachedCard(
        $cache,
        new FilesystemCatalogImages($cache, CardCatalogModule::provider()),
    ))->execute($id);
}
function ygo_card_search(string $query, string $format, int $page): array
{
    return CardCatalogModule::create()->search->execute($query, $format, $page);
}
function ygo_card_catalog_api(): never
{
    try {
        (new CatalogController(CardCatalogModule::create()))
            ->handle($_SERVER['REQUEST_METHOD'] ?? '', $_GET)
            ->emit();
    } catch (Throwable $error) {
        CatalogController::failure($error)->emit();
    }
    exit();
}

function ygo_deck_builder_snapshot(PDO $pdo, int $id, bool $lock = false): array
{
    $q = $pdo->prepare(
        'SELECT id,name FROM sx_ygo_decks WHERE id=? AND active=1' . ($lock ? ' FOR UPDATE' : ''),
    );
    $q->execute([$id]);
    $deck = $q->fetch(PDO::FETCH_ASSOC);
    if (!$deck) {
        throw new InvalidArgumentException('El deck no existe.');
    }
    $q = $pdo->prepare(
        'SELECT * FROM sx_ygo_deck_cards WHERE deck_id=? ORDER BY id' .
            ($lock ? ' FOR UPDATE' : ''),
    );
    $q->execute([$id]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    return [
        'deck' => $deck,
        'cards' => $rows,
        'version' => hash(
            'sha256',
            json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ),
    ];
}

/** Preserve annotations when a draft stack is dropped onto the same card. */
function ygo_card_merge_annotations(array $old, array $source, int $cardId, mixed $merged): array
{
    return \StationX\Yugioh\Domain\Decks\CardAnnotations::cardMergeAnnotations(
        $old,
        $source,
        $cardId,
        $merged,
    );
}

function ygo_deck_builder_save(PDO $pdo, int $id, array $input): array
{
    return ygo_with_event_write_lock($pdo, static function () use ($pdo, $id, $input) {
        $owns = !$pdo->inTransaction();
        if ($owns) {
            $pdo->beginTransaction();
        }
        try {
            $before = ygo_deck_builder_snapshot($pdo, $id, true);
            if (!hash_equals($before['version'], (string) ($input['version'] ?? ''))) {
                throw new InvalidArgumentException(
                    'La lista cambió desde que la abriste. Recárgala antes de guardar.',
                );
            }
            $rows = $input['cards'] ?? null;
            if (!is_array($rows) || !array_is_list($rows) || count($rows) > 300) {
                throw new InvalidArgumentException('Lista de cartas inválida.');
            }
            $old = array_column($before['cards'], null, 'id');
            $normalized = [];
            $seen = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('Carta inválida.');
                }
                $section = (string) ($row['section'] ?? '');
                $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
                $existing = (int) ($row['existing_id'] ?? 0);
                $cardId = (int) ($row['card_id'] ?? 0);
                if (
                    !in_array($section, ['main', 'extra', 'side', 'reserve'], true) ||
                    !$quantity ||
                    $quantity < 1 ||
                    $quantity > 99
                ) {
                    throw new InvalidArgumentException(
                        'Revisa la sección y la cantidad de cada carta (1 a 99).',
                    );
                }
                if ($existing && !isset($old[$existing])) {
                    throw new InvalidArgumentException('La carta no pertenece a esta lista.');
                }
                $source = $existing ? $old[$existing] : [];
                $source = ygo_card_merge_annotations(
                    $old,
                    $source,
                    $cardId,
                    $row['merged_ids'] ?? [],
                );
                if (($input['documented_import'] ?? false) === true) {
                    // Owner-approved documentary inventories may contain cards absent from the image catalog.
                    // Konami CIDs are provenance, never substituted for YGOPRODeck image IDs.
                    $name = trim((string) ($row['card_name'] ?? ''));
                    if ($name === '' || mb_strlen($name) > 160) {
                        throw new InvalidArgumentException('Nombre documental inválido.');
                    }
                    if ($cardId) {
                        ygo_card_lookup($cardId);
                    }
                    $key = $section . ':documented:' . mb_strtolower($name);
                    $source = [
                        'role' => mb_substr(trim((string) ($row['role'] ?? '')), 0, 80) ?: null,
                        'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                    ];
                    if (strlen((string) $source['notes']) > 4000) {
                        throw new InvalidArgumentException(
                            'La anotación documental supera el tamaño permitido.',
                        );
                    }
                } elseif ($cardId) {
                    $card = ygo_card_lookup($cardId);
                    $name = $card['name'];
                    $key = $section . ':card:' . $cardId;
                } else {
                    if (!$existing || !empty($source['ygoprodeck_id'])) {
                        throw new InvalidArgumentException('Selecciona una carta del buscador.');
                    }
                    $name = $source['card_name'];
                    $key = 'legacy:' . $existing;
                }
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException(
                        'La misma carta está repetida en una sección. Ajusta su cantidad.',
                    );
                }
                $seen[$key] = true;
                $normalized[] = [
                    'card_name' => $name,
                    'role' => $source['role'] ?? null,
                    'quantity' => $quantity,
                    'section' => $section,
                    'notes' => $source['notes'] ?? null,
                    'ygoprodeck_id' => $cardId ?: null,
                ];
            }
            $pdo->prepare('DELETE FROM sx_ygo_deck_cards WHERE deck_id=?')->execute([$id]);
            $insert = $pdo->prepare(
                'INSERT INTO sx_ygo_deck_cards(deck_id,card_name,role,quantity,section,notes,ygoprodeck_id) VALUES(?,?,?,?,?,?,?)',
            );
            foreach ($normalized as $r) {
                $insert->execute([
                    $id,
                    $r['card_name'],
                    $r['role'],
                    $r['quantity'],
                    $r['section'],
                    $r['notes'],
                    $r['ygoprodeck_id'],
                ]);
            }
            $after = ygo_deck_builder_snapshot($pdo, $id);
            $pdo->prepare(
                'INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)',
            )->execute([
                'deck',
                $id,
                'save_decklist',
                json_encode(
                    ['before' => $before['cards'], 'after' => $after['cards']],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            ]);
            if ($owns) {
                $pdo->commit();
            }
            return ['ok' => true] + $after;
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    });
}

function ygo_deck_builder_api(PDO $pdo): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $id = (int) ($_GET['deck_id'] ?? 0);
        $_SESSION['deck_builder_token'] ??= bin2hex(random_bytes(24));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!ygo_can_edit()) {
                throw new InvalidArgumentException('No tienes permiso para editar el deck.');
            }
            $raw = file_get_contents('php://input');
            if (strlen($raw) > 100000) {
                throw new InvalidArgumentException('La lista supera el tamaño permitido.');
            }
            $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (
                !is_array($input) ||
                !hash_equals($_SESSION['deck_builder_token'], (string) ($input['token'] ?? ''))
            ) {
                throw new InvalidArgumentException('La sesión cambió. Vuelve a abrir el editor.');
            }
            $answer = ygo_deck_builder_save($pdo, $id, $input);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $answer =
                [
                    'ok' => true,
                    'token' => $_SESSION['deck_builder_token'],
                    'can_edit' => ygo_can_edit(),
                ] + ygo_deck_builder_snapshot($pdo, $id);
        } else {
            throw new InvalidArgumentException('Operación no permitida.');
        }
        echo json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        http_response_code($e instanceof InvalidArgumentException ? 422 : 503);
        echo json_encode(
            [
                'ok' => false,
                'message' =>
                    $e instanceof JsonException
                        ? 'Los datos de la lista no son válidos.'
                        : ($e instanceof PDOException
                            ? 'No se pudo guardar la lista. Inténtalo de nuevo.'
                            : $e->getMessage()),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    }
    exit();
}
