<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionHonorPolicy
{
    public static function usesFinal(array $competition): bool
    {
        return in_array((string) ($competition['type'] ?? ''), ['cup', 'final', 'world'], true) ||
            in_array(
                (string) ($competition['execution_model'] ?? ''),
                ['single_elimination', 'double_elimination', 'bracket', 'series'],
                true,
            );
    }

    public static function fromFinal(array $final): array
    {
        return $final['result'] === 'A'
            ? [(int) $final['deck_a_id'], (int) $final['deck_b_id']]
            : [(int) $final['deck_b_id'], (int) $final['deck_a_id']];
    }

    public static function fromRanking(array $ranking): array
    {
        return [(int) ($ranking[0]['id'] ?? 0), (int) ($ranking[1]['id'] ?? 0)];
    }
}
