<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decklists;

/** Snapshot order and version must reflect the stored inventory; lock=true protects edits. */
interface DecklistReader
{
    public function snapshot(int $deckId, bool $lock = false): array;
}
