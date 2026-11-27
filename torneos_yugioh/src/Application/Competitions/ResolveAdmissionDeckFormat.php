<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;

final readonly class ResolveAdmissionDeckFormat
{
    public function __construct(private AdmissionSourceReader $reader) {}

    public function execute(array $ids): string
    {
        if (!$ids) {
            throw new InvalidArgumentException('La fuente no tiene decks elegibles.');
        }
        $aliases = [];
        foreach ($this->reader->rulesets() as $r) {
            $aliases[strtolower($r['code'])] = $r['code'];
            $aliases[strtolower($r['label'])] = $r['code'];
        }
        $formats = [];
        foreach ($this->reader->deckFormats($ids) as $r) {
            $style = $aliases[strtolower(trim((string) $r['style']))] ?? null;
            $owned = $r['event_ruleset'] ?: null;
            if ($owned === 'custom') {
                $owned = null;
            }
            if ($style && $owned && $style !== $owned) {
                throw new InvalidArgumentException(
                    'El formato del deck contradice el de su liga. Corrige su registro antes de clasificarlo.',
                );
            }
            $format = $owned ?: $style;
            if (!$format) {
                throw new InvalidArgumentException(
                    'No se puede confirmar el formato de un deck de la fuente.',
                );
            }
            $formats[$format] = true;
        }
        if (count($formats) !== 1) {
            throw new InvalidArgumentException('La fuente mezcla decks de distintos formatos.');
        }
        return (string) array_key_first($formats);
    }
}
