<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use InvalidArgumentException;

final class PackageFolder
{
    public static function normalizeFiles(array $files): array
    {
        $out = [];
        foreach ($files as $path => $bytes) {
            $path = preg_replace('~^(?:01_INFORMACION_PARA_IMPORTAR/decks/|decks/)~', '', $path);
            if (isset($out[$path])) {
                throw new InvalidArgumentException(
                    'Dos archivos del ZIP tienen la misma ruta de deck.',
                );
            }
            $out[$path] = $bytes;
        }
        return $out;
    }

    public static function folder(array $files, int $id, string $name): string
    {
        $folders = [];
        foreach (array_keys($files) as $path) {
            foreach (explode('/', $path) as $part) {
                if (str_starts_with($part, 'DECK_')) {
                    $folders[$part] = true;
                    break;
                }
            }
        }
        if (count($folders) > 1) {
            throw new InvalidArgumentException('El ZIP debe contener una sola carpeta de deck.');
        }
        if ($folders) {
            return array_key_first($folders);
        }
        $slug = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_'));
        return 'DECK_' . $id . '_' . ($slug ?: 'DECK');
    }

    public static function checkFolder(array $files, array $sources, array $inventory): string
    {
        $id = (int) $inventory['deck']['id'];
        $name = $inventory['deck']['name'];
        $expected = PackageFolder::folder($sources, $id, $name);
        $found = PackageFolder::folder($files, $id, $name);
        if ($found !== $expected) {
            throw new InvalidArgumentException(
                'La carpeta del ZIP no corresponde a esta ficha. Se esperaba ' . $expected . '.',
            );
        }
        return $found;
    }
}
