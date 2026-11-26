<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\CompetitionCodePolicy;

final readonly class ManageCompetitionCodes
{
    public function __construct(private CompetitionCodeStore $store) {}

    public function next(int $seasonId, int $ignoreId): string
    {
        $counter = CompetitionCodePolicy::nextCounter($this->store->codes($seasonId, $ignoreId));
        do {
            $candidate = CompetitionCodePolicy::code($counter++);
        } while ($this->store->exists($seasonId, $candidate, $ignoreId));
        return $candidate;
    }

    public function ensureSequential(int $seasonId): int
    {
        $updated = 0;
        foreach (
            CompetitionCodePolicy::replacements($this->store->competitions($seasonId))
            as [$id, $code]
        ) {
            $this->store->update($seasonId, $id, $code);
            $updated++;
        }
        return $updated;
    }
}
