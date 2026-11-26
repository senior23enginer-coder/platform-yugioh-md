<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Competitions\LeagueRosterPolicy;

final readonly class ValidateFormatRoster
{
    public function __construct(private FormatRosterReader $reader) {}

    public function execute(string $ruleset, array $ids): array
    {
        $ids = LeagueRosterPolicy::normalize($ids);
        if ($ruleset === 'custom' && $ids) {
            $formats = $this->reader->activeFormats();
            $commonFormat = false;
            foreach ($formats as $format) {
                if (!array_diff($ids, $this->reader->deckIds((string) $format))) {
                    $commonFormat = true;
                    break;
                }
            }
            if (!$commonFormat) {
                throw new InvalidArgumentException(
                    'No se pueden mezclar decks de distintos formatos en un mismo evento.',
                );
            }
        }
        if (array_diff($ids, $this->reader->deckIds($ruleset))) {
            throw new InvalidArgumentException(
                'Hay decks que no pertenecen al formato del evento. Revisa los participantes antes de guardar.',
            );
        }
        return $ids;
    }
}
