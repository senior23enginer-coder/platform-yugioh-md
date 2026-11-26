<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionConfigurationMedia
{
    public function upload(): string;

    public function validGalleryLogo(string $url, string $type): string;

    public function gallery(): array;

    public function recordHistory(string $entity, int $id, string $url, string $source): void;
}
