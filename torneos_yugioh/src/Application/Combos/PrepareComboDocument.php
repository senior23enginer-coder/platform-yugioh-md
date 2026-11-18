<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

use StationX\Yugioh\Domain\Combos\ComboGraphPolicy;

/** Validate first, then verify the documented identity through the source port. */
final readonly class PrepareComboDocument
{
    public function __construct(private ComboSources $sources) {}
    public function execute(array $input, array $inventory): array
    {
        return ComboGraphPolicy::prepare($input, $inventory) +
            $this->sources->reference($input, (int) $inventory['deck']['id']);
    }
}
