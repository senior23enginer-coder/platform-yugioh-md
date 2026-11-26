<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

use StationX\Yugioh\Domain\Decks\DeckCardInput;

final readonly class AddDeckCard
{
    public function __construct(private DeckCardWriter $writer) {}

    public function execute(array $input): string
    {
        $row = DeckCardInput::single($input);
        if ($row !== null) {
            $this->writer->append($row);
        }
        return 'carta_deck';
    }
}
