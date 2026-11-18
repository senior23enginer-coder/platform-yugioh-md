<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use Throwable;

interface NewsFailureMessage
{
    public function for(Throwable $error): string;
}
