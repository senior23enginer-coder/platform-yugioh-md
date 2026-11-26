<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;
use StationX\Yugioh\Application\Decks\Packages\DeckPackageArchive;
use StationX\Yugioh\Domain\Decks\Packages\PackageFiles;

final class ZipDeckPackageArchive implements DeckPackageArchive
{
    public function read(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'El servidor necesita la extensión PHP ZIP para esta herramienta.',
            );
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('No se pudo abrir el ZIP.');
        }
        try {
            if ($zip->numFiles > 3000) {
                throw new InvalidArgumentException('El ZIP supera los 3000 archivos.');
            }
            $files = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $s = $zip->statIndex($i);
                $name = PackageFiles::path($s['name']);
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $total += (int) $s['size'];
                if (
                    $total > 120 * 1024 * 1024 ||
                    $s['size'] > 24 * 1024 * 1024 ||
                    ($s['size'] > 1024 * 1024 && $s['size'] / max(1, $s['comp_size']) > 300)
                ) {
                    throw new InvalidArgumentException(
                        'El ZIP supera el límite de contenido descomprimido.',
                    );
                }
                if (
                    isset($files[$name]) ||
                    !preg_match('~\.(md|txt|json|png|jpe?g|webp|gif|html|svg)$~i', $name)
                ) {
                    throw new InvalidArgumentException(
                        'Archivo duplicado o tipo no admitido: ' . $name,
                    );
                }
                $opsys = 0;
                $attrs = 0;
                $zip->getExternalAttributesIndex($i, $opsys, $attrs);
                if ((($attrs >> 16) & 0170000) === 0120000) {
                    throw new InvalidArgumentException('No se admiten enlaces simbólicos.');
                }
                $bytes = $zip->getFromIndex($i);
                if (
                    $bytes === false ||
                    strlen($bytes) !== (int) $s['size'] ||
                    sprintf('%u', crc32($bytes)) !== sprintf('%u', $s['crc'])
                ) {
                    throw new InvalidArgumentException('Archivo ZIP dañado.');
                }
                $files[$name] = $bytes;
            }
            return $files;
        } finally {
            $zip->close();
        }
    }

    public function write(string $path, array $files, bool $create): void
    {
        $zip = new ZipArchive();
        $flags = ZipArchive::OVERWRITE | ($create ? ZipArchive::CREATE : 0);
        if ($zip->open($path, $flags) !== true) {
            throw new RuntimeException(
                $create ? 'No se pudo guardar el original.' : 'No se pudo preparar el ZIP.',
            );
        }
        foreach ($files as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        if (!$zip->close()) {
            throw new RuntimeException(
                $create ? 'No se pudo cerrar el original.' : 'No se pudo cerrar el ZIP.',
            );
        }
    }
}
