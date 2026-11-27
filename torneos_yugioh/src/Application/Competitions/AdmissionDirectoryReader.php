<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface AdmissionDirectoryReader
{
    public function completedCompetitions(): array;

    public function divisions(int $id): array;
}
