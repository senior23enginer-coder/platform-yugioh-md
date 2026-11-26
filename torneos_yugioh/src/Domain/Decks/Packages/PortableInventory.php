<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use InvalidArgumentException;

final class PortableInventory
{
    public static function validate(
        array $envelope,
        array $inventory,
        string $folder,
        ?array $current,
    ): void {
        if (
            ($envelope['inventory_binding'] ?? '') !== 'deck-slots-v1' ||
            ($envelope['content_mode'] ?? '') !== 'structured' ||
            ($envelope['package_root'] ?? '') !== $folder ||
            !is_array($envelope['inventory'] ?? null)
        ) {
            throw new InvalidArgumentException(
                'Paquete GPT portátil incompleto o con carpeta incorrecta.',
            );
        }
        if (
            isset($envelope['inventory_sha256']) &&
            $envelope['inventory_sha256'] !== $inventory['inventory_sha256']
        ) {
            throw new InvalidArgumentException(
                'El inventario de la plataforma cambió; vuelve a exportar Data.',
            );
        }
        $read = static function (array $rows, string $source): array {
            $found = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('Fila inválida en ' . $source);
                }
                $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
                $qty = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
                $zone = $row['section'] ?? null;
                if (
                    !$id ||
                    $id < 1 ||
                    !is_string($zone) ||
                    !in_array($zone, ['main', 'extra', 'side', 'reserve', 'tech'], true) ||
                    $qty === false ||
                    $qty < 1 ||
                    $qty > 3 ||
                    isset($found[$id])
                ) {
                    throw new InvalidArgumentException(
                        'Inventario duplicado o incompatible en ' . $source,
                    );
                }
                $found[$id] = [$zone, $qty];
            }
            ksort($found, SORT_NUMERIC);
            return $found;
        };
        $native = $read($inventory['cards'] ?? [], 'Station X');
        $declared = $read($envelope['inventory'], 'paquete GPT');
        if (!$native || $native !== $declared) {
            throw new InvalidArgumentException(
                'Las cartas del ZIP GPT no coinciden exactamente con el deck guardado en Station X.',
            );
        }
        $record = $envelope['record'] ?? [];
        if (!is_array($record) || !is_array($record['cards'] ?? null)) {
            throw new InvalidArgumentException('Faltan fichas del deck.');
        }
        // Older decks can be absent from deck-deliveries.json; that source returns
        // "unspecified" even though their verified current study has a real format.
        $declaredFormat = (string) ($inventory['format'] ?? 'unspecified');
        $expectedFormat = match ($declaredFormat) {
            'tcg', 'tcg_advanced' => 'tcg_advanced',
            'rush', 'rush_duel' => 'rush_duel',
            'goat' => 'goat',
            'tcg_traditional' => 'tcg_traditional',
            default => 'unspecified',
        };
        if ($expectedFormat === 'unspecified') {
            $studyFormat = $current['format'] ?? null;
            if (
                !is_array($current) ||
                ($current['schema'] ?? null) !== 'station-x-curated-v62' ||
                (int) ($current['deck_id'] ?? 0) !== (int) $inventory['deck']['id'] ||
                !is_string($studyFormat) ||
                !in_array(
                    $studyFormat,
                    ['tcg_advanced', 'tcg_traditional', 'rush_duel', 'goat'],
                    true,
                )
            ) {
                throw new InvalidArgumentException(
                    'No se pudo verificar el formato actual del deck. Exporta Data desde Station X.',
                );
            }
            $expectedFormat = $studyFormat;
        }
        if (($record['format'] ?? '') !== $expectedFormat) {
            throw new InvalidArgumentException(
                'El formato del paquete GPT no corresponde al deck.',
            );
        }

        $slots = [];
        foreach ($record['cards'] as $card) {
            if (!is_array($card) || !is_array($card['slots'] ?? null)) {
                throw new InvalidArgumentException('Ficha sin cartas.');
            }
            foreach ($card['slots'] as $slot) {
                $slots[] = [
                    'id' => $slot['card_id'] ?? null,
                    'section' => $slot['section'] ?? null,
                    'quantity' => $slot['quantity'] ?? null,
                ];
            }
        }
        if ($read($slots, 'fichas GPT') !== $native) {
            throw new InvalidArgumentException(
                'El JSON de estudio omitió o modificó cartas respecto al inventario real.',
            );
        }
    }
}
