<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class StandingSnapshotPolicy
{
    public static function scopes(array $phases): array
    {
        $snapshotScopes = ['current' => null];
        foreach (array_filter(array_map('strval', $phases)) as $phase) {
            $snapshotScopes['phase:' . $phase] = $phase;
        }
        return $snapshotScopes;
    }

    public static function rows(array $standings, string $reason, ?string $phase): iterable
    {
        foreach ($standings as $index => $row) {
            $deckId = (int) ($row['id'] ?? 0);
            if ($deckId <= 0) {
                continue;
            }
            yield [
                $deckId,
                $index + 1,
                (int) ($row['pts'] ?? 0),
                (int) ($row['pj'] ?? 0),
                (int) ($row['pg'] ?? 0),
                (int) ($row['pe'] ?? 0),
                (int) ($row['pp'] ?? 0),
                (int) ($row['cg'] ?? 0),
                (int) ($row['cp'] ?? 0),
                (int) ($row['dp'] ?? 0),
                ['reason' => $reason, 'phase' => $phase, 'row' => $row],
            ];
        }
    }
}
