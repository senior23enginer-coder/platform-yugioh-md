<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckProfileServices
{
    public function team(string $name, string $archetype): int;

    public function uploadCover(int $id): string;

    public function recordCover(int $id, string $url): void;
}
