<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

interface ComboSourceFeedReader
{
    public function metadata(): array;

    public function records(): iterable;
}
