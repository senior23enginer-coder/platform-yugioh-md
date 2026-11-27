<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;
use JsonException;

final class AdmissionFormInput
{
    public static function parse(array $post): array
    {
        try {
            $rows = json_decode(
                (string) ($post['admission_sources_json'] ?? '[]'),
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                'Las fuentes recibidas no tienen un formato valido.',
            );
        }
        if (!is_array($rows)) {
            throw new InvalidArgumentException('Fuentes invalidas.');
        }
        $capacity = filter_var($post['admission_capacity'] ?? 0, FILTER_VALIDATE_INT);
        if ($capacity === false) {
            throw new InvalidArgumentException('El total de plazas debe ser entero.');
        }
        $id = (int) ($post['competition_id'] ?? 0);
        return [$id, $rows, $capacity];
    }
}
