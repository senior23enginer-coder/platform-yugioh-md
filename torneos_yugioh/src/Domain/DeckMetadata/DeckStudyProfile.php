<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\DeckMetadata;

/** Identified study fields. Storage paths and source-document keys stay in the adapter. */
final readonly class DeckStudyProfile
{
    public function __construct(
        public mixed $strategy = '',
        public mixed $style = '',
        public mixed $archetype = null,
    ) {}
}
