<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

function ygo_event_progress(array $rounds, bool $perRound): array
{
    return \StationX\Yugioh\Domain\Competitions\EventProgressPolicy::calculate($rounds, $perRound);
}

function ygo_event_progress_groups(PDO $pdo): array
{
    return (new \StationX\Yugioh\Application\Competitions\ReadEventProgress(
        new \StationX\Yugioh\Infrastructure\Persistence\Competitions\PdoEventProgressReader($pdo),
    ))->execute();
}
