<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\News;

use PDO;
use StationX\Yugioh\Application\News\NewsReadStore;
use StationX\Yugioh\Domain\News\NewsFilter;

/** Parameterized native search, ordering and counts. Interval values come from a validated domain object. */
final readonly class PdoNewsReadStore implements NewsReadStore
{
    public function __construct(private PDO $pdo) {}
    private function conditions(NewsFilter $f): array
    {
        $where = [];
        $params = [];
        foreach (
            ['source_key' => $f->source, 'format_code' => $f->format, 'topic' => $f->topic]
            as $column => $value
        ) {
            if ($value) {
                $where[] = $column . '=?';
                $params[] = $value;
            }
        }
        if ($f->view === 'unread') {
            $where[] = 'read_at IS NULL';
        }
        if ($f->view === 'saved') {
            $where[] = 'bookmarked=1';
        }
        if ($f->query !== '') {
            $where[] = '(title LIKE ? OR excerpt LIKE ?)';
            $params[] = '%' . $f->query . '%';
            $params[] = '%' . $f->query . '%';
        }
        if ($f->days) {
            $where[] = 'published_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . $f->days . ' DAY)';
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function count(NewsFilter $filter): int
    {
        [$clause, $params] = $this->conditions($filter);
        $q = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_news_items' . $clause);
        $q->execute($params);
        return (int) $q->fetchColumn();
    }

    public function items(NewsFilter $filter, int $page): array
    {
        [$clause, $params] = $this->conditions($filter);
        $q = $this->pdo->prepare(
            'SELECT id,source_key,url,title,excerpt,image_url,published_at,date_precision,format_code,topic,first_seen_at,updated_at,read_at,bookmarked FROM sx_ygo_news_items' .
                $clause .
                ' ORDER BY (published_at IS NULL) ASC,published_at DESC,first_seen_at DESC,id DESC LIMIT 12 OFFSET ' .
                ($page - 1) * 12,
        );
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeDecks(): array
    {
        return $this->pdo
            ->query('SELECT id,name FROM sx_ygo_decks WHERE active=1 ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sourceStates(): array
    {
        return $this->pdo
            ->query('SELECT * FROM sx_ygo_news_sources ORDER BY source_key')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sourceCount(string $source): int
    {
        $q = $this->pdo->prepare('SELECT COUNT(*) FROM sx_ygo_news_items WHERE source_key=?');
        $q->execute([$source]);
        return (int) $q->fetchColumn();
    }

    public function counts(): array
    {
        return [
            'unread' => (int) $this->pdo
                ->query('SELECT COUNT(*) FROM sx_ygo_news_items WHERE read_at IS NULL')
                ->fetchColumn(),
            'saved' => (int) $this->pdo
                ->query('SELECT COUNT(*) FROM sx_ygo_news_items WHERE bookmarked=1')
                ->fetchColumn(),
        ];
    }
}
