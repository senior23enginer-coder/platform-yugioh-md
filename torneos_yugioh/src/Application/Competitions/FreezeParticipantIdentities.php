<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\ParticipantIdentityPolicy;

final class FreezeParticipantIdentities
{
    public function __construct(private readonly ParticipantIdentityReader $reader)
    {
    }

    public function execute(array $ids, string $format): array
    {
        if (!$ids) {
            return [];
        }
        return ParticipantIdentityPolicy::freeze($ids, $this->reader->activeDecks($ids), $format);
    }
}
