<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Combos;

final readonly class ReadComboSourceFeed
{
    public function __construct(
        private ComboSourceFeedReader $reader,
        private ComboDocumentRoutes $routes,
        private ComboSources $sources,
    ) {}

    public function execute(string $ifNoneMatch): array
    {
        $etag = self::etag($this->reader->metadata());
        if ($ifNoneMatch === $etag) {
            return ['status' => 304, 'etag' => $etag, 'body' => null];
        }
        $records = [];
        $hashRows = [];
        foreach ($this->reader->records() as $row) {
            $hashRows[] = [
                'id' => $row['id'],
                'deck_id' => $row['deck_id'],
                'revision' => $row['revision'],
                'updated_at' => $row['updated_at'],
            ];
            $route = $this->routes->routes((int) $row['deck_id'])[$row['id']] ?? null;
            if (!$route) {
                continue;
            }
            $record = ComboRecordView::unpack($row, $row['inventory_sha256']);
            $record['document']['source_route_id'] = $route;
            $records[] = [
                'deck_id' => (int) $row['deck_id'],
                'id' => $row['id'],
                'route_id' => $route,
                'revision' => (int) $row['revision'],
                'updated_at' => $row['updated_at'],
                'document' => $record['document'],
                'cards_snapshot' => $record['cards_snapshot'],
                'markdown' => $this->sources->markdown($record),
            ];
        }
        return [
            'status' => 200,
            'etag' => self::etag($hashRows),
            'body' => ['ok' => true, 'schema' => 'station-x-combo-source/1', 'records' => $records],
        ];
    }

    private static function etag(array $rows): string
    {
        return '"' . hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)) . '"';
    }
}
