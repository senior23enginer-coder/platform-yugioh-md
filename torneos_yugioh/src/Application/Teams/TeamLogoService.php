<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Teams;

interface TeamLogoService
{
    public function upload(int $id): string;

    public function record(int $id, string $url): void;
}
