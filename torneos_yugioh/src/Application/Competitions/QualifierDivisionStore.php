<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface QualifierDivisionStore
{
    public function division(int $divisionId): array;

    public function qualifierStatus(int $qualifierId): string;

    public function standings(int $qualifierId): array;

    public function assign(int $divisionId, iterable $assignments): int;

    public function record(int $divisionId, int $qualifierId, int $rankFrom, int $rankTo, int $count): void;
}
