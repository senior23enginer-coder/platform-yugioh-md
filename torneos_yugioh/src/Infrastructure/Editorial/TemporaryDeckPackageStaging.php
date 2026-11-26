<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use RuntimeException;
use StationX\Yugioh\Application\Decks\Packages\DeckPackageStaging;

final class TemporaryDeckPackageStaging implements DeckPackageStaging
{
    public function stage(string $upload): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'ygo-import-');
        if ($temp === false || !copy($upload, $temp)) {
            if ($temp !== false) {
                $this->discard($temp);
            }
            throw new RuntimeException('No se pudo preparar la carga.');
        }
        return $temp;
    }

    public function discard(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function ticket(): string
    {
        return bin2hex(random_bytes(24));
    }
}
