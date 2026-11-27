<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

interface DeckTemplateWriter
{
    public function activeNameExists(string $name): bool;

    public function create(array $data): int;

    public function appendCards(int $id, array $cards): void;
}
