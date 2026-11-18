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
    return \StationX\Yugioh\Infrastructure\Composition\DecklistModule::reader($pdo)->snapshot(
        $id,
        $lock,
    );
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

/** Public facade retains caller-owned event locking and transaction semantics. */
function ygo_deck_builder_save(PDO $pdo, int $id, array $input): array
{
    return \StationX\Yugioh\Infrastructure\Composition\DecklistModule::save($pdo)->execute(
        $id,
        $input,
    );
}

function ygo_deck_builder_api(PDO $pdo): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $id = (int) ($_GET['deck_id'] ?? 0);
        $_SESSION['deck_builder_token'] ??= bin2hex(random_bytes(24));
        $controller = new \StationX\Yugioh\Infrastructure\Http\Decklists\DecklistController(
            \StationX\Yugioh\Infrastructure\Composition\DecklistModule::reader($pdo),
            \StationX\Yugioh\Infrastructure\Composition\DecklistModule::save($pdo),
        );
        $controller
            ->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',
                $id,
                $_SESSION['deck_builder_token'],
                static fn() => ygo_can_edit(),
                static fn() => (string) file_get_contents('php://input'),
            )
            ->emit();
    } catch (Throwable $error) {
        \StationX\Yugioh\Infrastructure\Http\Decklists\DecklistController::failure($error)->emit();
    }
    exit();
}
