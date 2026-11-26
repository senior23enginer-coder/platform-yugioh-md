<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

use StationX\Yugioh\Domain\Combos\ComboSourcePolicy;
use StationX\Yugioh\Infrastructure\Editorial\NativeComboSources;
use StationX\Yugioh\Infrastructure\Editorial\ComboSourceMarkdown;
use StationX\Yugioh\Application\Combos\ReadComboSourceFeed;
use StationX\Yugioh\Infrastructure\Persistence\Combos\PdoComboSourceFeedReader;

function ygo_combo_source_id(int $deckId, string $route): string
{
    return ComboSourcePolicy::id($deckId, $route);
}

function ygo_combo_source_routes(int $deckId): array
{
    return (new NativeComboSources())->routes($deckId);
}

function ygo_combo_source_reference(array $input, int $deckId): array
{
    return (new NativeComboSources())->reference($input, $deckId);
}

function ygo_combo_source_markdown(array $record): string
{
    return ComboSourceMarkdown::render($record);
}

function ygo_combo_source_feed(PDO $pdo): never
{
    $sources = new NativeComboSources();
    $response = (new ReadComboSourceFeed(
        new PdoComboSourceFeedReader($pdo),
        $sources,
        $sources,
    ))->execute($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    header('ETag: ' . $response['etag']);
    if ($response['status'] === 304) {
        http_response_code(304);
        exit();
    }
    echo json_encode(
        $response['body'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    exit();
}
