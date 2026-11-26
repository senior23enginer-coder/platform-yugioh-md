<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks\Packages;

use DateTimeZone;
use StationX\Yugioh\Application\Shared\Clock;
use StationX\Yugioh\Domain\Decks\Packages\PackageFiles;
use StationX\Yugioh\Domain\Decks\Packages\PackagePublicationPolicy;

final readonly class PublishDeckPackage
{
    public function __construct(private DeckPackagePublication $storage, private Clock $clock) {}

    public function execute(int $id, array $record, array $files, string $expected): array
    {
        return $this->storage->underLock(function () use ($id, $record, $files, $expected): array {
            $state = $this->storage->registry();
            $old = $state['checked_studies'][(string) $id] ?? null;
            PackagePublicationPolicy::assertUnchanged($old, $expected);
            $version = $this->storage->version($id);
            $this->storage->createVersion($version);
            $prefix = './assets/editorial/' . $version . '/';
            $map = [];
            foreach (PackagePublicationPolicy::images($files, $prefix) as $image) {
                $this->storage->writeImage($version, $image['destination'], $image['bytes']);
                $map[$image['name']] = $image['url'];
            }
            $record = PackagePublicationPolicy::record($record, $map, $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('c'));
            $json = PackageFiles::json($record);
            $this->storage->writeStudy($version, $id, $json);
            $this->storage->writeOriginal($version, $files);
            $entry = PackagePublicationPolicy::entry($id, $prefix, $record, $json);
            $state['checked_studies'][(string) $id] = $entry;
            $state['version'] = $version;
            $this->storage->writePrevious($version, PackageFiles::json($old));
            $this->storage->activate(PackageFiles::json($state));
            return $entry;
        });
    }
}
