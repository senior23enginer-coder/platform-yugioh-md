<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface PlayerStore
{
    public function save(array $profile): int;

    public function replaceDecks(int $id, array $deckIds): void;

    public function audit(int $id, int $teamId, array $deckIds): void;
}
