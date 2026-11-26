<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckRemovalStore
{
    public function archive(int $id): void;

    public function auditBlocked(int $id, array $blocker): void;
}
