<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface MatchSnapshotStore
{
    public function match(int $matchId): array;

    public function deck(int $deckId): array;

    public function decklist(int $deckId): array;

    public function insert(array $row): void;
}
