<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Settings;

final readonly class ReadSettings
{
    public function __construct(private SettingsReader $reader) {}

    public function settings(int $seasonId, array $defaults): array
    {
        $settings = $defaults;
        foreach ($this->reader->settings($seasonId) as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $settings;
    }

    public function labels(int $seasonId, array $defaults): array
    {
        $labels = [];
        foreach ($this->reader->labels($seasonId) as $row) {
            $labels[(string) $row['label_key']] = [
                'id' => (int) $row['id'],
                'key' => (string) $row['label_key'],
                'title' => (string) ($row['title'] ?? ''),
                'subtitle' => (string) ($row['subtitle'] ?? ''),
                'active' => (int) ($row['active'] ?? 1),
            ];
        }
        foreach ($defaults as $label) {
            if (!isset($labels[$label[0]])) {
                $labels[$label[0]] = [
                    'id' => 0,
                    'key' => $label[0],
                    'title' => $label[1],
                    'subtitle' => $label[2],
                    'active' => 1,
                ];
            }
        }
        ksort($labels);
        return $labels;
    }
}
