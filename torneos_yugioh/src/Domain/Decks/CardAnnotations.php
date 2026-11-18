<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

use InvalidArgumentException;

/** Merges annotations only for explicitly selected inventory identities and supported sections. */
final class CardAnnotations
{
    public static function cardMergeAnnotations(
        array $old,
        array $source,
        int $cardId,
        mixed $merged,
    ): array {
        if (!is_array($merged) || !array_is_list($merged) || count($merged) > 300) {
            throw new InvalidArgumentException('Referencias de cartas inv?lidas.');
        }
        foreach (array_unique($merged) as $mergedId) {
            $mergedId = filter_var($mergedId, FILTER_VALIDATE_INT);
            if (
                !$mergedId ||
                !isset($old[$mergedId]) ||
                (int) ($old[$mergedId]['ygoprodeck_id'] ?? 0) !== $cardId ||
                !$cardId
            ) {
                throw new InvalidArgumentException('No se pueden combinar estas cartas.');
            }
            $notes = trim((string) ($old[$mergedId]['notes'] ?? ''));
            $role = trim((string) ($old[$mergedId]['role'] ?? ''));
            if (empty($source['role'])) {
                $source['role'] = $role ?: null;
            } elseif ($role !== '' && $source['role'] !== $role) {
                $notes = trim($notes . "\n\nFunci?n de la carta movida: " . $role);
            }
            if (
                $notes !== '' &&
                !in_array($notes, explode("\n\n", (string) ($source['notes'] ?? '')), true)
            ) {
                $source['notes'] =
                    trim((string) ($source['notes'] ?? '')) .
                    (empty($source['notes']) ? '' : "\n\n") .
                    $notes;
            }
        }
        return $source;
    }
}
