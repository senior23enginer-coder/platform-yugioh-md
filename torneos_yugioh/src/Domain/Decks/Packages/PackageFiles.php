<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use InvalidArgumentException;

final class PackageFiles
{
    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function path(string $name): string
    {
        $name=str_replace('\\','/',$name);
        if($name===''||strlen($name)>240||str_starts_with($name,'/')||preg_match('~(^|/)\.\.?(/|$)|[:\x00-\x1f]~',$name))throw new InvalidArgumentException('El ZIP contiene una ruta no permitida.');
        return $name;
    }

    public static function sourceImage(array $files,string $value): ?string
    {
        $value=preg_replace('~^\./~','',$value);$matches=[];
        foreach($files as $path=>$bytes)if($path===$value||str_ends_with($path,'/'.$value))$matches[]=$path;
        return count($matches)===1?$matches[0]:null;
    }
}
