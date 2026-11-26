<?php
declare(strict_types=1);

/**
 * event-configuration: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_configure_competition(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionConfigurationModule::create($pdo)->execute(
        $_POST,
        $GLOBALS['competitionTypeOptions'] ?? [],
    );
}

function ygo_delete_competition(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\CompetitionDeletionModule::create($pdo)->execute(
        (int) ($_POST['competition_id'] ?? 0),
        (string) ($_POST['delete_confirmation'] ?? ''),
    );
}
