<?php
declare(strict_types=1);

/**
 * database-read-model: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_load_data(PDO $pdo, array $fallbackDivisions, array $fallbackFixtures, array $fallbackDecks): array
{
    return \StationX\Yugioh\Infrastructure\Composition\PlatformReadModelModule::create($pdo)->execute($fallbackDivisions);
}
