<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

/** Expose actual seat subresults, never insert synthetic matches into sporting tables. */
function ygo_stats_team_source(array $db,array $base,?array $record): array {
    return \StationX\Yugioh\Domain\Statistics\MultiplayerSourceProjection::team($db, $base, $record);
}

function ygo_stats_ffa_source(PDO $p): array {
    return \StationX\Yugioh\Domain\Statistics\MultiplayerSourceProjection::freeForAll((new \StationX\Yugioh\Infrastructure\Persistence\Statistics\PdoStatisticsSourceReader($p))->freeForAll());
}
