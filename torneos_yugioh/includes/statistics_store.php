<?php
declare(strict_types=1);
require_once __DIR__.'/statistics_v8.php';
require_once __DIR__.'/statistics_multiplayer_source.php';

function ygo_stats_canonical(mixed $value): string {
    return \StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy::canonical($value);
}

function ygo_stats_schema(PDO $p): void {
    \StationX\Yugioh\Infrastructure\Persistence\Statistics\StatisticsSchema::ensure($p);
}

/** Stable source facts. Fallback names, winners, tier or historical dates are forbidden. */
function ygo_stats_source(PDO $p): array {
    return (new \StationX\Yugioh\Application\Statistics\ReadStatisticsSource(new \StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsSourceReader($p)))->execute();
}

function ygo_stats_sync(PDO $p): int {
    return \StationX\Yugioh\Infrastructure\Composition\StatisticsModule::refresh($p)->execute();
}

function ygo_stats_publications(PDO $p,bool $full=true): array {
    return (new \StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsRevisionStore($p))->publications($full);
}

function ygo_stats_public_values(array $data): array {
    return \StationX\Yugioh\Domain\Statistics\StatisticsRevisionPolicy::publicValues($data);
}

function ygo_stats_configure_global(PDO $p,string $anchor,array $mapping=[]): void {
    \StationX\Yugioh\Infrastructure\Composition\StatisticsModule::configuration($p)->execute($anchor, $mapping);
}

/** Authorized rollout defaults; never overwrite an already fixed policy. */
function ygo_stats_bootstrap_global(PDO $p): void {
    \StationX\Yugioh\Infrastructure\Composition\StatisticsModule::configuration($p)->bootstrap();
}

function ygo_stats_view(PDO $p): array {
    return \StationX\Yugioh\Infrastructure\Composition\StatisticsModule::view($p)->execute();
}

/** Legacy publication entry point: new cuts are retired; historical records remain readable. */
function ygo_stats_publish(PDO $p,string $kind,string $scope,string $cutAt,string $reason): int {
    throw new InvalidArgumentException('Los cortes estadísticos fueron retirados. La clasificación se actualiza con los resultados confirmados.');
}

/** Compatibility for old cron callers: never create another scheduled cut. */
function ygo_stats_publish_due(PDO $p,int $limit=8): int { return 0; }
