<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

/** Native read DTO. Preserve the serialized-document and inventory snapshot contract. */
final class ComboRecordView
{
    public static function unpack(array $row, string $hash): array
    {
        return [
            'id' => $row['id'],
            'deck_id' => (int) $row['deck_id'],
            'title' => $row['title'],
            'revision' => (int) $row['revision'],
            'inventory_sha256' => $row['inventory_sha256'],
            'stale' => $row['inventory_sha256'] !== $hash,
            'cards_snapshot' => json_decode($row['inventory_json'], true, 128, JSON_THROW_ON_ERROR),
            'document' => json_decode($row['document_json'], true, 128, JSON_THROW_ON_ERROR),
            'updated_at' => $row['updated_at'],
        ];
    }
}
