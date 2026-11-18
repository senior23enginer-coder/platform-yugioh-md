<?php
declare(strict_types=1);
namespace StationX\Yugioh\Infrastructure\Persistence;

use StationX\Yugioh\Application\Seasons\SeasonRepository;
use StationX\Yugioh\Domain\Seasons\Season;

/** Reuse the already-loaded ordered read model; do not introduce a second database query. */
final readonly class LoadedSeasonRepository implements SeasonRepository
{
    public function __construct(private array $rows) {}

    public function newestFirst(): iterable
    {
        foreach ($this->rows as $row) {
            yield new Season((int) ($row['id'] ?? 0), (string) ($row['name'] ?? ''), (string) ($row['status'] ?? ''));
        }
    }
}
