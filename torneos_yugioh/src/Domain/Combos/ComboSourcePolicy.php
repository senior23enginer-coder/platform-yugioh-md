<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Combos;

use InvalidArgumentException;

final class ComboSourcePolicy
{
    public static function id(int $deckId, string $route): string
    {
        $a = 2166136261;
        $b = 5381;
        foreach (mb_str_split($deckId . ':' . $route) as $char) {
            $code = unpack('v', mb_convert_encoding($char, 'UTF-16LE', 'UTF-8'))[1];
            $a = (($a ^ $code) * 16777619) & 0xffffffff;
            $b = (($b * 33) ^ $code) & 0xffffffff;
        }
        return sprintf('documented-%d-%08x%08x', $deckId, $a, $b);
    }

    public static function reference(array $input, array $routes): array
    {
        $route = $routes[$input['id'] ?? ''] ?? null;
        if (isset($input['source_route_id']) && $input['source_route_id'] !== $route) {
            throw new InvalidArgumentException('El combo no corresponde a esa ruta documental.');
        }
        return $route ? ['source_route_id' => $route] : [];
    }

    public static function context(array $record): array
    {
        $result = ['format' => 'unspecified', 'skills' => []];
        $result['format'] = match ($record['format'] ?? '') {
            'rush_duel' => 'rush',
            'goat' => 'goat',
            'tcg_advanced', 'tcg_traditional' => 'tcg',
            default => 'unspecified',
        };
        if ($result['format'] === 'rush') {
            foreach ($record['skills'] ?? [] as $skill) {
                if (empty($skill['skill_id'])) {
                    continue;
                }
                $result['skills'][] = [
                    'id' => (string) $skill['skill_id'],
                    'name' => (string) ($skill['nombre'] ?? $skill['skill_id']),
                    'requirements' => (string) ($skill['requisito_texto'] ?? ''),
                    'effect' => (string) ($skill['efecto_texto'] ?? ''),
                    'image' => (string) ($skill['image_url'] ?? ''),
                    'source' => (string) ($skill['origen_verificado'] ?? ''),
                    'limits' => $skill['limitations_unverified'] ?? [],
                ];
            }
        }
        return $result;
    }
}
