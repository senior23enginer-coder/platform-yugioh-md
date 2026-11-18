<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

/** Optional manual-study fields; no interpretation of card effects. */
function ygo_combo_context(int $deckId): array
{
    $root = dirname(__DIR__, 2) . '/assets/editorial';
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
    $file = realpath(dirname(__DIR__, 2) . '/' . substr($relative, 2));
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
    $result['format'] = match ($record['format'] ?? '') {
        'rush_duel' => 'rush',
        'goat' => 'goat',
        'tcg_advanced', 'tcg_traditional' => 'tcg',
        default => 'unspecified',
    };
    if ($result['format'] === 'rush') {
        foreach ($record['skills'] ?? [] as $skill) {
            if (empty($skill['skill_id'])) {
                continue;
            }
            $result['skills'][] = [
                'id' => (string) $skill['skill_id'],
                'name' => (string) ($skill['nombre'] ?? $skill['skill_id']),
                'requirements' => (string) ($skill['requisito_texto'] ?? ''),
                'effect' => (string) ($skill['efecto_texto'] ?? ''),
                'image' => (string) ($skill['image_url'] ?? ''),
                'source' => (string) ($skill['origen_verificado'] ?? ''),
                'limits' => $skill['limitations_unverified'] ?? [],
            ];
        }
    }
    return $result;
}

function ygo_combo_details(array $node, array $cards, string $format): array
{
    return \StationX\Yugioh\Domain\Combos\ComboStepPolicy::details($node, $cards, $format);
}
