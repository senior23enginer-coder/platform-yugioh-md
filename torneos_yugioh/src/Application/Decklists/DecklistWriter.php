<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decklists;

/** All replacement rows and their before/after audit share the caller's transaction. */
interface DecklistWriter
{
    public function replace(int $deckId, array $cards): void;
    public function audit(int $deckId, array $before, array $after): void;
}
