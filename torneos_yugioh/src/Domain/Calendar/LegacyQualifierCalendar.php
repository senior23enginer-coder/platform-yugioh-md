<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Calendar;

use DateTimeImmutable;

final readonly class LegacyQualifierCalendar
{
    public function __construct(
        public string $startDate,
        public string $startTime,
        public int $matchesPerDay,
        public int $dayInterval,
        public int $minutesPerMatch,
        public DateTimeImmutable $base,
    ) {}

    public static function fromInput(array $input, string $today): self
    {
        $startDate = (string) ($input['qualifier_schedule_start'] ?? $today);
        $startTime = (string) ($input['qualifier_schedule_time'] ?? '19:00');
        $matchesPerDay = max(1, min(5, (int) ($input['qualifier_matches_per_day'] ?? 1)));
        $dayInterval = max(1, min(7, (int) ($input['qualifier_day_interval'] ?? 1)));
        $minutesPerMatch = max(20, min(180, (int) ($input['qualifier_schedule_minutes'] ?? 45)));
        $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime)
            ?: new DateTimeImmutable($today . ' 19:00');
        return new self($startDate, $startTime, $matchesPerDay, $dayInterval, $minutesPerMatch, $base);
    }

    public function fixtures(array $pairs): iterable
    {
        $total = count($pairs);
        foreach (array_values($pairs) as $index => $pair) {
            [$dayIndex, $slot] = $this->position($total, $index);
            $dateTime = $this->base
                ->modify('+' . ($dayIndex * $this->dayInterval) . ' days')
                ->modify('+' . ($slot * $this->minutesPerMatch) . ' minutes');
            yield [
                'round' => $pair[0],
                'table' => $pair[1],
                'deckA' => $pair[2],
                'deckB' => $pair[3],
                'date' => $dateTime->format('Y-m-d'),
                'time' => $dateTime->format('H:i:s'),
            ];
        }
    }

    public function days(int $total): array
    {
        $days = [];
        for ($index = 0; $index < $total; $index++) {
            [$dayIndex] = $this->position($total, $index);
            $day = $this->base->modify('+' . ($dayIndex * $this->dayInterval) . ' days')->format('Y-m-d');
            $days[$day] = ($days[$day] ?? 0) + 1;
        }
        return $days;
    }

    public function endDate(array $days, int $total): string
    {
        $keys = array_keys($days);
        return $keys
            ? (string) end($keys)
            : $this->base
                ->modify('+' . (intdiv(max(0, $total - 1), $this->matchesPerDay) * $this->dayInterval) . ' days')
                ->format('Y-m-d');
    }

    private function position(int $total, int $index): array
    {
        $dayIndex = intdiv($index, $this->matchesPerDay);
        $slot = $index % $this->matchesPerDay;
        if ($total % $this->matchesPerDay === 1 && $index === $total - 1 && $dayIndex > 0) {
            $dayIndex--;
            $slot = $this->matchesPerDay;
        }
        return [$dayIndex, $slot];
    }
}
