<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

final class CompetitionCodePolicy
{
    public static function nextCounter(array $codes): int
    {
        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^YU-(\d+)$/', (string) $code, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }
        return $max + 1;
    }

    public static function code(int $counter): string
    {
        return 'YU-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
    }

    public static function replacements(array $competitions): iterable
    {
        $used = [];
        $max = 0;
        foreach ($competitions as $competition) {
            $code = (string) ($competition['code'] ?? '');
            if (preg_match('/^YU-(\d+)$/', $code, $match)) {
                $number = (int) $match[1];
                $max = max($max, $number);
                $used['YU-' . str_pad((string) $number, 2, '0', STR_PAD_LEFT)] = true;
            } elseif ($code !== '') {
                $used[$code] = true;
            }
        }
        $counter = $max + 1;
        foreach ($competitions as $competition) {
            $currentCode = (string) ($competition['code'] ?? '');
            if (preg_match('/^YU-\d{2,}$/', $currentCode)) {
                continue;
            }
            do {
                $candidate = self::code($counter);
                $counter++;
            } while (isset($used[$candidate]));
            $used[$candidate] = true;
            yield [(int) $competition['id'], $candidate];
        }
    }
}
