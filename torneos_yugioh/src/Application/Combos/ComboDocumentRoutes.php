<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

interface ComboDocumentRoutes
{
    public function routes(int $deckId): array;
}
