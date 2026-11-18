<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

/** Current records and immutable revisions share the transaction supplied by the caller. */
interface ComboRepository
{
    public function find(string $id): ?array;
    public function forDeck(int $deckId): array;
    public function history(string $id): array;
    public function persist(array $record, bool $exists, int $expectedRevision): void;
    public function appendRevision(array $record, string $createdAt): void;
}
