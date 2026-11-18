<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

use StationX\Yugioh\Application\DeckMetadata\BuildDeckListMetadata;
use StationX\Yugioh\Infrastructure\Editorial\FilesystemDeckStudyReader;

/**
 * Native read-model facade. Keep the signature and array fields consumed by list and detail views.
 * Rules live in Domain/DeckMetadata; the filesystem adapter only reads identified study fields.
 */
function ygo_deck_list_metadata(
    array $decks,
    array $cards,
    array $profiles,
    ?string $app = null,
): array {
    $reader = new FilesystemDeckStudyReader($app ?? dirname(__DIR__, 2));
    return (new BuildDeckListMetadata($reader))->execute($decks, $cards, $profiles);
}
