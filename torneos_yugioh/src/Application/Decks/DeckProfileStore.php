<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckProfileStore
{
    public function team(int $id): ?int;

    public function cover(int $id): string;

    public function save(int $id, array $profile): int;

    public function updateCover(int $id, string $url): void;
}
