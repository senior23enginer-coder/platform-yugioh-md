<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\ReadModel;

interface CompetitionReadModelServices
{
    public function dependencies(): array;

    public function progressGroups(): array;

    public function progress(array $rows, bool $knockout): array;

    public function gate(int $id): array;

    public function allowedDecks(int $id, int $divisionId): array;

    public function standings(int $id): array;
}
