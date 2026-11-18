<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/autoload.php';
require_once dirname(__DIR__) . '/league_configuration.php';

use StationX\Yugioh\Application\DeckWorlds\AssignWorldsCommand;
use StationX\Yugioh\Domain\DeckWorlds\WorldCatalog;
use StationX\Yugioh\Domain\DeckWorlds\WorldSelection;
use StationX\Yugioh\Infrastructure\Composition\DeckWorldsModule;
use StationX\Yugioh\Infrastructure\Http\DeckWorlds\WorldAssignmentPresenter;
use StationX\Yugioh\Infrastructure\Persistence\DeckWorlds\DeckWorldsSchema;
use StationX\Yugioh\Infrastructure\Persistence\DeckWorlds\WorldAssignmentCodec;

/**
 * Compatibility entry points for existing callers.
 * Rules belong to Domain/DeckWorlds; use cases depend on ports, not these functions.
 */
function ygo_deck_world_eligible_ids(PDO $pdo): array
{
    return DeckWorldsModule::create($pdo)->query->eligibleIds();
}

function ygo_deck_attribute_catalog(): array
{
    return WorldCatalog::LABELS;
}

/** Retain the legacy distinction between new selections and readable historical tombstones. */
function ygo_deck_attributes_validate(mixed $attributes, bool $allowEmpty = false): array
{
    return ($allowEmpty
        ? WorldSelection::fromStored($attributes)
        : WorldSelection::fromInput($attributes)
    )->codes;
}

/** Explicit schema initialization; constructing domain objects never creates tables. */
function ygo_deck_attributes_schema(PDO $pdo): void
{
    DeckWorldsSchema::ensure($pdo);
}

function ygo_deck_attributes_unpack(array $row): array
{
    return WorldAssignmentPresenter::one(WorldAssignmentCodec::fromRow($row));
}

function ygo_deck_attributes_records(PDO $pdo): array
{
    return WorldAssignmentPresenter::many(DeckWorldsModule::create($pdo)->query->records());
}

function ygo_deck_attributes_history(PDO $pdo, int $id): array
{
    return WorldAssignmentPresenter::many(DeckWorldsModule::create($pdo)->query->history($id));
}

/** The use case owns the complete deck-lock/current-assignment/history transaction. */
function ygo_deck_attributes_save(PDO $pdo, array $input): array
{
    return WorldAssignmentPresenter::one(
        DeckWorldsModule::create($pdo)->assign->execute(AssignWorldsCommand::fromArray($input)),
    );
}
