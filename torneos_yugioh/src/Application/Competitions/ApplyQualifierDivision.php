<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\QualifierDivisionPolicy;

final readonly class ApplyQualifierDivision
{
    public function __construct(private QualifierDivisionStore $store) {}

    public function execute(int $divisionId): int
    {
        if ($divisionId <= 0) {
            return 0;
        }
        $range = QualifierDivisionPolicy::range($this->store->division($divisionId));
        if ($range === null) {
            return 0;
        }
        [$qualifierId, $rankFrom, $rankTo] = $range;
        if (!QualifierDivisionPolicy::completed($this->store->qualifierStatus($qualifierId))) {
            return 0;
        }
        $ranking = $this->store->standings($qualifierId);
        $slice = array_slice($ranking, $rankFrom - 1, ($rankTo - $rankFrom) + 1);
        if (!$slice) {
            return 0;
        }
        $count = $this->store->assign($divisionId, QualifierDivisionPolicy::assignments($slice));
        $this->store->record($divisionId, $qualifierId, $rankFrom, $rankTo, $count);
        return $count;
    }
}
