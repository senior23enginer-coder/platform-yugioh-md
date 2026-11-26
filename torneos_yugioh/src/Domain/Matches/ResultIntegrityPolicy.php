<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

use InvalidArgumentException;

final class ResultIntegrityPolicy
{
    public static function assertCorrectionVersion(mixed $existingDetails, int $submittedVersion): void
    {
        $correctionAt = max(array_merge([0], array_map(
            static fn($d) => (int) ($d['manual_correction_at_ms'] ?? 0),
            is_array($existingDetails) ? $existingDetails : [],
        )));
        if ($correctionAt > 0 && $submittedVersion !== $correctionAt) {
            throw new InvalidArgumentException('La partida fue modificada. Recarga antes de guardar para no sobrescribir la corrección.');
        }
    }

    public static function preserveStoredValues(array $existingValues, array $values): array
    {
        $firstWritable = 0;
        while ($firstWritable < 5 && $existingValues[$firstWritable] !== '') {
            $firstWritable++;
        }
        for ($index = 0; $index < 5; $index++) {
            if ($index < $firstWritable) {
                $values[$index] = $values[$index] ?: $existingValues[$index];
                continue;
            }
            if ($index > $firstWritable) {
                $values[$index] = $existingValues[$index] ?: '';
            }
        }
        return $values;
    }

    public static function appendTurnRows(array $details): iterable
    {
        foreach ($details as $detailRow) {
            $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
            foreach (TurnHistory::normalizeTurnHistory(is_array($detailRow['turn_history'] ?? null) ? $detailRow['turn_history'] : []) as $turnRow) {
                yield [
                    'duel' => $duelNumber,
                    'turn' => (int) $turnRow['turn'],
                    'side' => (string) $turnRow['side'],
                    'endedAt' => (string) $turnRow['ended_at'] !== '' ? (string) $turnRow['ended_at'] : null,
                    'elapsed' => (int) $turnRow['elapsed_seconds'],
                ];
            }
        }
    }
}
