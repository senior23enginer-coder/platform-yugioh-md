<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';

/** Deck-only editorial packages. No SQL writes, archive extraction or executable uploads. */
function ygo_data_json(mixed $value): string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFiles::json($value);
}
function ygo_data_read(string $path): array {
    return \StationX\Yugioh\Infrastructure\Editorial\DeckPackageJsonFile::read($path);
}
function ygo_data_path(string $name): string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFiles::path($name);
}
function ygo_data_local(string $app,string $url): ?string {
    return (new \StationX\Yugioh\Infrastructure\Editorial\LocalDeckPackageMedia($app))->resolve($url);
}
function ygo_data_source_image(array $files,string $value): ?string {
    return \StationX\Yugioh\Domain\Decks\Packages\PackageFiles::sourceImage($files, $value);
}
function ygo_data_archive(string $path): array {
    return (new \StationX\Yugioh\Infrastructure\Editorial\ZipDeckPackageArchive())->read($path);
}
function ygo_data_sections(string $raw): array {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::sections($raw);
}
function ygo_data_plain(string $s): string {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::plain($s);
}
function ygo_data_photos(string $text,array $cards): array {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::photos($text, $cards);
}
/** Preserve one explicit simulator contract; prose never becomes an executable effect. */
function ygo_data_calculator(string $raw,array $data,int $deckId): array {
    return \StationX\Yugioh\Domain\Decks\Packages\CalculatorContract::extract($raw, $data, $deckId);
}
/** Keep simulator metadata in the package, outside player-facing explanations. */
function ygo_data_without_calculator(string $raw): string {
    return \StationX\Yugioh\Domain\Decks\Packages\CalculatorContract::withoutContract($raw);
}
function ygo_data_markdown(string $raw,array $base,int $deckId): array {
    return \StationX\Yugioh\Domain\Decks\Packages\MarkdownStudy::parse($raw, $base, $deckId);
}
/** Presentation readiness is separate from legal rulings and simulator coverage. */
function ygo_data_presentation_quality(array $record): array {
    return \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::presentationQuality($record);
}

/**
 * A01 profile contract is opt-in for newly certified GPT packages.
 * Older records remain readable but are not automatically certified A01-T4/T5.
 */
function ygo_data_validate_a01_profile(array $record): void {
    \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::validateA01Profile($record);
}


/** Structured player guide: never certify a deck from editorial prose alone. */
function ygo_data_validate_player_guide(array $record): void {
    \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::validatePlayerGuide($record);
}

function ygo_data_validate(array $record,array $inventory): void {
    \StationX\Yugioh\Domain\Decks\Packages\StudyValidation::validate($record, $inventory);
}
function ygo_data_reader(string $name,string $raw): string {
    return \StationX\Yugioh\Infrastructure\Editorial\DeckPackageReaderHtml::render($name, $raw);
}
/** Atomic pointer replacement under one global lock; immutable old revisions remain available. */
function ygo_data_publish(string $app,int $id,array $record,array $files,string $expected): array {
    return \StationX\Yugioh\Infrastructure\Composition\DeckPackageModule::publish($app)->execute($id, $record, $files, $expected);
}
