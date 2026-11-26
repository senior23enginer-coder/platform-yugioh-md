<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface ParticipantCheckinStore
{
    public function save(array $input): void;

    public function audit(array $input): void;
}
