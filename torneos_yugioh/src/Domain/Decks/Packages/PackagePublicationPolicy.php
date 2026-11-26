<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use DomainException;
use InvalidArgumentException;

final class PackagePublicationPolicy
{
    public static function assertUnchanged(mixed $old, string $expected): void
    {
        if (hash('sha256', PackageFiles::json($old)) !== $expected) {
            throw new DomainException(
                'El deck cambió desde la previsualización. Vuelve a seleccionar el ZIP.',
            );
        }
    }

    public static function images(array $files, string $prefix): iterable
    {
        foreach ($files as $name => $bytes) {
            if (!preg_match('/\.(png|jpe?g|webp|gif)$/i', $name)) {
                continue;
            }
            $info = @getimagesizefromstring($bytes);
            $extensions = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            if (!$info || !isset($extensions[$info['mime']])) {
                throw new InvalidArgumentException('Imagen inválida: ' . $name);
            }
            $destination = hash('sha256', $bytes) . '.' . $extensions[$info['mime']];
            yield [
                'name' => $name,
                'destination' => $destination,
                'bytes' => $bytes,
                'url' => $prefix . $destination,
            ];
        }
    }

    public static function record(array $record, array $map, string $importedAt): array
    {
        array_walk_recursive($record, static function (&$value) use ($map) {
            if (is_string($value) && isset($map[$value])) {
                $value = $map[$value];
            }
        });
        unset($record['folder_delivery']['reader']);
        $record['folder_delivery']['source_file'] = 'ZIP importado desde la ficha del deck';
        $record['folder_delivery']['imported_at'] = $importedAt;
        return $record;
    }

    public static function entry(int $id, string $prefix, array $record, string $json): array
    {
        return [
            'path' => $prefix . $id . '.json',
            'cards' => count($record['cards']),
            'routes' => count($record['routes']),
            'sha256' => hash('sha256', $json),
            'presentation' => 'curated-v62',
            'source_zip' => $prefix . 'source.zip',
        ];
    }
}
