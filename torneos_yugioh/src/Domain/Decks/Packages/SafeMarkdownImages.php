<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use InvalidArgumentException;

final class SafeMarkdownImages
{
    public static function normalize(string $markdown, array $files): string
    {
        // A historical Markdown label is not a file: URI. Normalize only the exact
        // editorial label before plain-text validation; keep the original ZIP intact.
        $markdown =
            preg_replace(
                '/^- \*\*reference file:\*\* /m',
                '- **Archivo de referencia:** ',
                $markdown,
            ) ??
            throw new InvalidArgumentException('No se pudo procesar la referencia documental.');
        return preg_replace_callback(
            '~<img\b[^>]*>~iu',
            static function (array $match) use ($files): string {
                $tag = $match[0];
                if (
                    !preg_match(
                        '~^<img\s+src="(imagenes/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+\.(?:png|jpe?g|webp|gif))"(?:\s+width="[0-9]{1,3}")?(?:\s+alt="[^"<>]*")?(?:\s+loading="lazy")?\s*/?>$~iuD',
                        $tag,
                        $parts,
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Etiqueta HTML de imagen no admitida. Usa imágenes locales sin atributos ejecutables.',
                    );
                }
                $source = PackageFiles::sourceImage($files, $parts[1]);
                if ($source === null) {
                    throw new InvalidArgumentException(
                        'La imagen del documento no existe en el ZIP: ' . $parts[1],
                    );
                }
                return '[Imagen local: ' . $parts[1] . ']';
            },
            $markdown,
        ) ?? throw new InvalidArgumentException('No se pudo procesar el Markdown.');
    }
}
