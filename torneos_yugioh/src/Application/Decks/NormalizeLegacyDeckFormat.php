<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Decks;

final readonly class NormalizeLegacyDeckFormat
{
    public function __construct(private LegacyFormatNormalizationStore $store) {}

    public function execute(): int
    {
        if ($this->store->alreadyNormalized()) {
            return 0;
        }
        $divisionId = $this->store->currentTcgDivision();
        if ($divisionId <= 0) {
            return 0;
        }
        $updated = $this->store->normalize($divisionId);
        $this->store->record($divisionId, $updated);
        return $updated;
    }
}
