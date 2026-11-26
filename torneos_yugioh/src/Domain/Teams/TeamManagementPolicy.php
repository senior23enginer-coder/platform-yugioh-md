<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Teams;

final class TeamManagementPolicy
{
    public static function ids(mixed $ids): array
    {
        return array_values(
            array_unique(
                array_filter(
                    array_map('intval', is_array($ids) ? $ids : []),
                    static fn(int $id): bool => $id > 0,
                ),
            ),
        );
    }

    public static function profile(array $input, string $existingLogo): array
    {
        return [
            'name' => trim((string) $input['team_name']),
            'city' => trim((string) $input['team_city']),
            'logo' => $existingLogo,
            'code' => strtoupper(substr(trim((string) ($input['team_code'] ?? '')), 0, 12)),
            'symbol' => strtoupper(substr(trim((string) ($input['team_symbol'] ?? '')), 0, 24)),
        ];
    }
}
