<?php
declare(strict_types=1);
require_once __DIR__.'/data-package.php';

function ygo_data_normalize_files(array $files): array {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFolder::normalizeFiles($files);
}

/** One self-contained deck folder, never a collection of loose files. */
function ygo_data_folder(array $files,int $id,string $name): string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFolder::folder($files, $id, $name);
}
function ygo_data_check_folder(array $files,array $sources,array $inventory): string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFolder::checkFolder($files, $sources, $inventory);
}
function ygo_data_export_files(string $app,array $inventory,?array $record,array $sources,array $saved): array {
    return \StationX\Yugioh\Infrastructure\Composition\DeckPackageModule::export($app)->execute($inventory, $record, $sources, $saved);
}
