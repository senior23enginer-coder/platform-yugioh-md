<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface QualifierFinalizationWriter
{
    public function assign(int $divisionId, int $deckId, int $rank): void;

    public function updateDivisionCount(int $divisionId, int $size): void;

    public function regenerateLeagues(int $seasonId): void;

    public function audit(int $competitionId, int $total): void;
}
