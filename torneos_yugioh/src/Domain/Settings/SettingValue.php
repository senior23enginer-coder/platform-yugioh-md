<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Settings;

final class SettingValue
{
    public static function enabled(array $settings, string $key): bool
    {
        return in_array((string) ($settings[$key] ?? '0'), ['1', 'true', 'yes', 'on'], true);
    }
}
