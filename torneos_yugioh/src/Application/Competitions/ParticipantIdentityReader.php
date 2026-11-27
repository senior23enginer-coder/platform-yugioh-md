<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface ParticipantIdentityReader
{
    public function activeDecks(array $ids): array;
}
