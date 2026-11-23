<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use StationX\Yugioh\Application\DeckMetadata\BuildDeckListMetadata;
use StationX\Yugioh\Application\DeckMetadata\DeckStudyReader;
use StationX\Yugioh\Application\DeckMetadata\DeckStudySnapshot;
use StationX\Yugioh\Domain\DeckMetadata\DeckStudyProfile;
use StationX\Yugioh\Domain\DeckMetadata\DeckMetadataResolver;
use StationX\Yugioh\Infrastructure\Editorial\FilesystemDeckStudyReader;

$count = 0;
function metadataCheck(bool $condition, string $message): void
{
    global $count;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $count++;
}

metadataCheck(
    DeckMetadataResolver::description('Consultar el plan de juego y las condiciones') === '',
    'The catalog must reject old player-facing placeholders',
);
metadataCheck(
    DeckMetadataResolver::description('Dictamen editorial PARCIAL_AVANZADO: Cementerio') === '',
    'The catalog must reject audit paragraphs',
);
metadataCheck(
    DeckMetadataResolver::archetype(
        'Mementotlán / Demoniherrero — estudio individual A01–A10 · iteración 2',
    ) === 'Mementotlán / Demoniherrero',
    'The catalog must strip research-iteration suffixes',
);

// Use the application without loading the native include, JSON files, sessions or PDO.
$reader = new class implements DeckStudyReader {
    public array $requests = [];
    public function read(array $deckIds): DeckStudySnapshot
    {
        $this->requests[] = $deckIds;
        return new DeckStudySnapshot(
            [4 => new DeckStudyProfile('Control', 'Combo', 'Known archetype')],
            [4],
            [4 => 7],
        );
    }
};
$input = [11 => ['id' => 4, 'name' => 'Deck', 'style' => 'TCG', 'archetype' => 'deck']];
$cards = [
    ['deck_id' => 4, 'card_name' => 'Main', 'quantity' => 3, 'section' => 'main'],
    ['deck_id' => 4, 'card_name' => 'Extra', 'quantity' => 1, 'section' => 'extra'],
    ['deck_id' => 4, 'card_name' => 'Side', 'quantity' => 2, 'section' => 'side'],
    ['deck_id' => 4, 'card_name' => 'Reserve', 'quantity' => 2, 'section' => 'reserve'],
    ['deck_id' => 4, 'card_name' => '  ', 'quantity' => 9],
    ['deck_id' => 4, 'card_name' => 'Zero', 'quantity' => 0],
    ['deck_id' => 99, 'card_name' => 'Another deck', 'quantity' => 99],
];
$profiles = [
    ['deck_id' => 4, 'play_style' => 'Retired style', 'revision' => 7],
    ['deck_id' => 4, 'play_style' => 'Current manual style', 'revision' => 8],
];
$before = serialize([$input, $cards, $profiles]);
$useCase = new BuildDeckListMetadata($reader);
$rows = $useCase->execute($input, $cards, $profiles);
metadataCheck(
    $reader->requests === [[4]],
    'Expected one study snapshot for the requested deck IDs',
);
metadataCheck(array_keys($rows) === [11], 'Native catalog keys changed');
metadataCheck(
    $rows[11]['display_style'] === 'Current manual style',
    'Fresh manual profile lost priority',
);
metadataCheck($rows[11]['display_archetype'] === 'Known archetype', 'Study archetype not resolved');
metadataCheck(
    $rows[11]['card_count'] === 8 && $rows[11]['has_cards'],
    'Saved-zone quantities changed',
);
metadataCheck(serialize([$input, $cards, $profiles]) === $before, 'A source input was mutated');
metadataCheck(!function_exists('ygo_deck_list_metadata'), 'Application loaded the native facade');
$withoutManual = $useCase->execute($input, [], []);
metadataCheck($withoutManual[11]['display_style'] === 'Control', 'Study strategy fallback missing');
metadataCheck(!$withoutManual[11]['has_cards'], 'Empty inventory gained a card indicator');

require dirname(__DIR__) . '/includes/decks/public-metadata.php';
$cases = json_decode(
    file_get_contents(__DIR__ . '/fixtures/deck-metadata-characterization.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$temp = sys_get_temp_dir() . '/ygo-deck-metadata-' . bin2hex(random_bytes(8));
mkdir($temp, 0777, true);

/** Remove only test-owned files beneath the unique temporary directory. Never follows links. */
function metadataRemoveFixture(string $directory): void
{
    foreach (new FilesystemIterator($directory) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            metadataRemoveFixture($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}
try {
    foreach ($cases as $i => $case) {
        $app = $temp . '/' . $i;
        mkdir($app . '/assets/editorial', 0777, true);
        if ($case['policy'] !== null) {
            file_put_contents(
                $app . '/assets/editorial/strategy-content-state.json',
                is_string($case['policy'])
                    ? $case['policy']
                    : json_encode($case['policy'], JSON_THROW_ON_ERROR),
            );
        }
        foreach ($case['files'] as $file => $document) {
            if (!is_dir(dirname($app . '/' . $file))) {
                mkdir(dirname($app . '/' . $file), 0777, true);
            }
            file_put_contents(
                $app . '/' . $file,
                is_string($document) ? $document : json_encode($document, JSON_THROW_ON_ERROR),
            );
        }
        $before = serialize([$case['decks'], $case['cards'], $case['profiles']]);
        $result = ygo_deck_list_metadata($case['decks'], $case['cards'], $case['profiles'], $app);
        metadataCheck(
            $result === $case['expected'],
            'Original characterization changed: ' . $case['name'],
        );
        metadataCheck(
            serialize([$case['decks'], $case['cards'], $case['profiles']]) === $before,
            'Fixture was mutated: ' . $case['name'],
        );
    }

    $app = $temp . '/refresh';
    mkdir($app . '/assets/editorial', 0777, true);
    $file = $app . '/assets/editorial/profile.json';
    $registry = $app . '/assets/editorial/strategy-content-state.json';
    file_put_contents(
        $registry,
        json_encode(
            ['checked_studies' => ['4' => ['path' => 'assets/editorial/profile.json']]],
            JSON_THROW_ON_ERROR,
        ),
    );
    file_put_contents(
        $file,
        json_encode(
            ['deck_id' => 4, 'profile' => ['Tipo de estrategia' => 'First']],
            JSON_THROW_ON_ERROR,
        ),
    );
    $filesystem = new FilesystemDeckStudyReader($app);
    $snapshot = $filesystem->read([4, 4, 99]);
    metadataCheck(
        array_keys($snapshot->profiles) === [4],
        'Duplicated or unknown identities introduced',
    );
    metadataCheck($snapshot->profiles[4]->strategy === 'First', 'Active study not loaded');
    file_put_contents(
        $file,
        json_encode(
            ['deck_id' => 4, 'profile' => ['Tipo de estrategia' => 'Changed']],
            JSON_THROW_ON_ERROR,
        ),
    );
    metadataCheck(
        $filesystem->read([4])->profiles[4]->strategy === 'Changed',
        'Study update hidden by a stale cache',
    );
    metadataCheck($snapshot->profiles[4]->strategy === 'First', 'Previous snapshot was mutated');
    file_put_contents($registry, json_encode(['checked_studies' => []], JSON_THROW_ON_ERROR));
    metadataCheck($filesystem->read([4])->profiles === [], 'Registry removal did not take effect');
    metadataCheck(
        (new FilesystemDeckStudyReader($temp . '/absent'))->read([4])->profiles === [],
        'Missing editorial directory introduced study data',
    );
} finally {
    metadataRemoveFixture($temp);
}
echo "PASS $count checks: metadata port, original characterization, identity/path isolation and source refresh.\n";
