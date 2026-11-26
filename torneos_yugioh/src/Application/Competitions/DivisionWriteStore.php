<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface DivisionWriteStore
{
    public function currentSeasonId(): int;
    public function save(int $id, int $seasonId, string $code, array $values): int;
    public function uploadLogo(int $id): string;
    public function setLogo(int $id, int $seasonId, string $url): void;
    public function assignDecks(int $id, array $decks): void;
    public function applyQualifier(int $id): void;
}
