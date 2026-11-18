<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\DeckWorlds;

use InvalidArgumentException;
use StationX\Yugioh\Domain\DeckWorlds\WorldSelection;

/** Validated command shared by HTTP and the compatibility entry point. */
final readonly class AssignWorldsCommand
{
    private function __construct(
        public int $deckId,
        public int $expectedRevision,
        public WorldSelection $selection,
    ) {}

    public static function fromArray(array $input): self
    {
        $id = filter_var($input['deck_id'] ?? null, FILTER_VALIDATE_INT);
        $expected = filter_var($input['expected_revision'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1 || $expected === false || $expected < 0) {
            throw new InvalidArgumentException('Deck o revisión no válidos.');
        }
        $action = $input['action'] ?? 'save';
        if (!in_array($action, ['save', 'remove'], true)) {
            throw new InvalidArgumentException('Acción no válida.');
        }
        if ($action === 'remove') {
            throw new InvalidArgumentException(
                'El deck debe conservar al menos un mundo. Añade otro antes de quitar el último.',
            );
        }
        return new self($id, $expected, WorldSelection::fromInput($input['attributes'] ?? null));
    }
}
