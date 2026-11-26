<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

use InvalidArgumentException;

final class DeckPackageJsonFile
{
    public static function read(string $path): array
    {
        $v=json_decode((string)file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
        if(!is_array($v))throw new InvalidArgumentException('Documento JSON inválido.');return $v;
    }
}
