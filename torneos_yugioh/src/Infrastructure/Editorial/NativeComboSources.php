<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use StationX\Yugioh\Application\Combos\ComboSources;
use StationX\Yugioh\Application\Combos\ComboDocumentRoutes;
use StationX\Yugioh\Domain\Combos\ComboSourcePolicy;

/** Editorial file adapter. No native global callbacks. */
final class NativeComboSources implements ComboSources, ComboDocumentRoutes
{
    private readonly string $app;
    private static array $cache = [];

    public function __construct(?string $app = null)
    {
        $this->app = $app ?? dirname(__DIR__, 3);
    }

    public function context(int $deckId): array
    {
        $root = $this->app . '/assets/editorial';
        $result = ['format' => 'unspecified', 'skills' => []];
        $manifest = $root . '/deck-deliveries.json';
        if (!is_file($manifest)) {
            return $result;
        }
        $data = json_decode(file_get_contents($manifest), true);
        $url = $data['decks'][(string) $deckId]['url'] ?? '';
        $relative = parse_url($url, PHP_URL_PATH);
        if (!is_string($relative) || !str_starts_with($relative, './assets/editorial/')) {
            return $result;
        }
        $file = realpath($this->app . '/' . substr($relative, 2));
        $allowed = realpath($root);
        if (
            !$file ||
            !$allowed ||
            !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $allowed) . '/') ||
            !str_ends_with($file, '.json')
        ) {
            return $result;
        }
        $record = json_decode(file_get_contents($file), true);
        if ((int) ($record['deckId'] ?? 0) !== $deckId) {
            return $result;
        }
        return ComboSourcePolicy::context($record);
    }

    public function routes(int $deckId): array
    {
        $cache = &self::$cache[$this->app];
        $cache ??= [];
        if (isset($cache[$deckId])) {
            return $cache[$deckId];
        }
        $app = $this->app;
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
                $result[ComboSourcePolicy::id($deckId, $r['id'])] = $r['id'];
            }
        }
        return $cache[$deckId] = $result;
    }

    public function reference(array $input, int $deckId): array
    {
        return ComboSourcePolicy::reference($input, $this->routes($deckId));
    }

    public function markdown(array $record): string
    {
        return ComboSourceMarkdown::render($record);
    }
}
