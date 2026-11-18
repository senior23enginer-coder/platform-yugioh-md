<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\DeckWorlds;

/** Stable world codes; their order and labels are part of the existing public contract. */
final class WorldCatalog
{
    public const LABELS = [
        'dark' => 'OSCURIDAD',
        'earth' => 'TIERRA',
        'light' => 'LUZ',
        'water' => 'AGUA',
        'wind' => 'VIENTO',
        'fire' => 'FUEGO',
    ];
}
