<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Composition;

use DateTimeZone;
use StationX\Yugioh\Application\Decks\Packages\ExportDeckPackage;
use StationX\Yugioh\Application\Decks\Packages\PublishDeckPackage;
use StationX\Yugioh\Infrastructure\Editorial\LocalDeckPackagePublication;
use StationX\Yugioh\Infrastructure\Editorial\LocalDeckPackageSources;
use StationX\Yugioh\Infrastructure\Editorial\ZipDeckPackageArchive;
use StationX\Yugioh\Infrastructure\Time\SystemClock;
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

    public static function publish(string $app): PublishDeckPackage
    {
        return new PublishDeckPackage(
            new LocalDeckPackagePublication($app, new ZipDeckPackageArchive()),
            new SystemClock(new DateTimeZone('UTC')),
        );
    }

    public static function sources(string $app): LocalDeckPackageSources
    {
        return new LocalDeckPackageSources(
            $app,
            new LocalDeckPackageMedia($app),
            new ZipDeckPackageArchive(),
        );
    }
}
