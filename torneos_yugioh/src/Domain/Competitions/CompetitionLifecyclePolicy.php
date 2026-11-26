<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionLifecyclePolicy
{
    public static function normalizeStatus(string $status): string
    {
        $status = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $status) ?: $status;
        $status = strtolower(trim($status));
        $status = preg_replace('/[^a-z0-9]+/', '_', $status) ?: '';
        return trim($status, '_');
    }

    public static function pendingQualifier(array $competition): bool
    {
        if ((string) ($competition['type'] ?? '') !== 'qualifier') {
            return false;
        }
        $status = self::normalizeStatus((string) ($competition['status'] ?? ''));
        if (in_array($status, ['pending_event', 'pendiente_evento'], true)) {
            return true;
        }
        $selectedDeckIds = $competition['selected_deck_ids'] ?? [];
        $selectedDeckCount = is_array($selectedDeckIds) ? count($selectedDeckIds) : 0;
        $deckCount = max($selectedDeckCount, (int) ($competition['deck_pool_count'] ?? 0));
        return $status === 'pending_configuration' && $deckCount >= 4;
    }

    public static function unfinishedWorkflow(array $workflow): ?array
    {
        if (!empty($workflow['complete'])) {
            return null;
        }
        $finalized = count($workflow['history']);
        $pending = count($workflow['pending'] ?? []);
        return [
            'ok' => false,
            'total' => $finalized + $pending,
            'finalized' => $finalized,
            'pending' => $pending,
            'reason' => 'Completa el reglamento mediante Crear siguiente fase; terminar una ronda no termina el evento.',
        ];
    }

    public static function finishCounts(array $row): array
    {
        $total = (int) ($row['total_matches'] ?? 0);
        $finalized = (int) ($row['finalized_matches'] ?? 0);
        return [
            'ok' => $total > 0 && $total === $finalized,
            'total' => $total,
            'finalized' => $finalized,
            'pending' => max(0, $total - $finalized),
        ];
    }

    public static function history(int $competitionId, ?string $fromStatus, string $toStatus): ?array
    {
        if ($competitionId <= 0 || trim($toStatus) === '') {
            return null;
        }
        $from = $fromStatus !== null ? self::normalizeStatus($fromStatus) : null;
        $to = self::normalizeStatus($toStatus);
        if ($from !== null && $from === $to) {
            return null;
        }
        return ['from' => $from, 'to' => $to];
    }
}
