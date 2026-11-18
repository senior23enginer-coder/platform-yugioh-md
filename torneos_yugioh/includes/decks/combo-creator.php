<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/autoload.php';
require_once __DIR__ . '/combo-creator-details.php';
require_once __DIR__ . '/combo-source.php';
use StationX\Yugioh\Infrastructure\Composition\ComboModule;
use StationX\Yugioh\Infrastructure\Persistence\Combos\ComboSchema;
use StationX\Yugioh\Application\Combos\ComboRecordView;
use StationX\Yugioh\Domain\Combos\ComboStepPolicy;

/** Native compatibility facade. Domain validation, application commands and SQL are separate. */
function ygo_combo_schema(PDO $pdo): void
{
    ComboSchema::ensure($pdo);
}
function ygo_combo_text(mixed $value, int $limit, bool $required = false): string
{
    return ComboStepPolicy::text($value, $limit, $required);
}
function ygo_combo_inventory(
    PDO $pdo,
    int $deckId,
    bool $lock = false,
    bool $requireMain = true,
): array {
    return ComboModule::create($pdo)->inventory->load($deckId, $lock, $requireMain);
}
function ygo_combo_document(array $input, array $inventory): array
{
    return (new \StationX\Yugioh\Application\Combos\PrepareComboDocument(
        new \StationX\Yugioh\Infrastructure\Editorial\NativeComboSources(),
    ))->execute($input, $inventory);
}
function ygo_combo_unpack(array $row, string $hash): array
{
    return ComboRecordView::unpack($row, $hash);
}
function ygo_combo_save(PDO $pdo, array $input): array
{
    return ComboModule::create($pdo)->save->execute($input);
}
