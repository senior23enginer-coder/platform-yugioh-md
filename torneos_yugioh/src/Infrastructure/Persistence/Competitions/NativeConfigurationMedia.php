<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\CompetitionConfigurationMedia;

final readonly class NativeConfigurationMedia implements CompetitionConfigurationMedia
{
    public function __construct(private PDO $pdo) {}

    public function upload(): string
    {
        return \ygo_store_event_logo_upload();
    }

    public function validGalleryLogo(string $url, string $type): string
    {
        return \ygo_valid_event_gallery_logo($url, $type);
    }

    public function gallery(): array
    {
        return \ygo_event_logo_gallery();
    }

    public function recordHistory(string $entity, int $id, string $url, string $source): void
    {
        \ygo_record_logo_history($this->pdo, $entity, $id, $url, $source);
    }
}
