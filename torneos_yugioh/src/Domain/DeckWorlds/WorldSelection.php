<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\DeckWorlds;

use InvalidArgumentException;

/** Ordered selection: one to three distinct worlds; the first is the primary world. */
final readonly class WorldSelection
{
    /** @param list<string> $codes */
    private function __construct(public array $codes) {}

    public static function fromInput(mixed $codes): self
    {
        return self::validate($codes, false);
    }

    /** Historical empty revisions remain readable; new assignments cannot be empty. */
    public static function fromStored(mixed $codes): self
    {
        return self::validate($codes, true);
    }

    private static function validate(mixed $codes, bool $allowEmpty): self
    {
        if (
            !is_array($codes) ||
            !array_is_list($codes) ||
            count($codes) > 3 ||
            (!$allowEmpty && !$codes)
        ) {
            throw new InvalidArgumentException('Selecciona entre uno y tres mundos.');
        }
        foreach ($codes as $code) {
            if (!is_string($code) || !array_key_exists($code, WorldCatalog::LABELS)) {
                throw new InvalidArgumentException('Mundo no válido.');
            }
        }
        if (count(array_unique($codes)) !== count($codes)) {
            throw new InvalidArgumentException('No puedes repetir mundos.');
        }
        return new self($codes);
    }

    public function primary(): ?string
    {
        return $this->codes[0] ?? null;
    }

    public function equals(self $other): bool
    {
        return $this->codes === $other->codes;
    }
}
