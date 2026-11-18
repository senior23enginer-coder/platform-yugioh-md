<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\News;

use InvalidArgumentException;

/** Validated native filters. Provider, topic and interval values never become arbitrary SQL. */
final readonly class NewsFilter
{
    private function __construct(
        public string $source,
        public string $format,
        public string $topic,
        public string $view,
        public string $query,
        public int $days,
        public int $page,
    ) {}
    public static function fromArray(array $input, array $providers): self
    {
        $source = (string) ($input['source'] ?? '');
        if ($source && !isset($providers[$source])) {
            throw new InvalidArgumentException('Fuente no válida.');
        }
        $values = [];
        foreach (
            [
                'format' => [
                    'tcg',
                    'ocg',
                    'rush_duel',
                    'goat',
                    'master_duel',
                    'genesys',
                    'general',
                ],
                'topic' => ['news', 'banlist', 'tournament', 'cards', 'guides'],
            ]
            as $key => $allowed
        ) {
            $value = (string) ($input[$key] ?? '');
            if ($value && !in_array($value, $allowed, true)) {
                throw new InvalidArgumentException('Filtro no válido.');
            }
            $values[$key] = $value ?: '';
        }
        $view = (string) ($input['view'] ?? 'all');
        if (!in_array($view, ['all', 'unread', 'saved'], true)) {
            throw new InvalidArgumentException('Vista no válida.');
        }
        $days = (int) ($input['days'] ?? 0);
        if ($days && !in_array($days, [7, 30, 90], true)) {
            throw new InvalidArgumentException('Período no válido.');
        }
        return new self(
            $source ?: '',
            $values['format'],
            $values['topic'],
            $view,
            NewsText::plain($input['q'] ?? '', 100),
            $days,
            (int) ($input['page'] ?? 1),
        );
    }
}
