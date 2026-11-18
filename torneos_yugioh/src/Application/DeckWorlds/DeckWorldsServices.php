<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds;

/** Services supplied by composition; HTTP never needs a connection or concrete repository. */
final readonly class DeckWorldsServices
{
    public function __construct(public AssignDeckWorlds $assign, public DeckWorldsQueries $query) {}
}
