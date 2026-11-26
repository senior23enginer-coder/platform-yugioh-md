<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use StationX\Yugioh\Application\Decks\Packages\ExportDeckPackage;
use StationX\Yugioh\Application\Decks\Packages\PrepareDeckPackage;
use StationX\Yugioh\Infrastructure\Editorial\LocalDeckPackageMedia;

final class DeckPackageModule
{
    public static function prepare(string $app): PrepareDeckPackage
    {
        return new PrepareDeckPackage(new LocalDeckPackageMedia($app));
    }

    public static function export(string $app): ExportDeckPackage
    {
        return new ExportDeckPackage(new LocalDeckPackageMedia($app));
    }
}
