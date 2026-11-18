<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Configured provider metadata; display colors and transport locations stay outside the domain. */
interface NewsProviders
{
    public function all(): array;
}
