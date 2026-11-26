<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use StationX\Yugioh\Application\Decks\Packages\DeckPackageMedia;
use StationX\Yugioh\Domain\Decks\Packages\PackageFiles;

final readonly class LocalDeckPackageMedia implements DeckPackageMedia
{
    public function __construct(private string $app) {}

    public function resolve(string $url): ?string
    {
        $url=preg_replace('~^\./~','',$url);
        if(!str_starts_with($url,'assets/editorial/')&&!str_starts_with($url,'assets/cards/'))return null;
        PackageFiles::path($url);$path=realpath($this->app.'/'.$url);$base=realpath($this->app.'/assets');
        return $path&&$base&&str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$base).'/')?$path:null;
    }

    public function exists(string $url): bool
    {
        return $this->resolve($url) !== null;
    }

    public function read(string $url): ?array
    {
        $path = $this->resolve($url);
        if ($path === null) {
            return null;
        }
        return [
            'sha256' => hash_file('sha256', $path),
            'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'bytes' => (string) file_get_contents($path),
        ];
    }
}
