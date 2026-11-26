<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamIndividualReader
{
    public function teamMatchIds(): array;

    public function records(): array;

    public function decks(): array;
}
