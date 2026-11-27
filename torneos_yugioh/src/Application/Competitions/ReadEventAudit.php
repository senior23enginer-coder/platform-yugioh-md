<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;

final class ReadEventAudit
{
    public function __construct(private readonly EventAuditReader $reader) {}

    public function snapshot(int $id): array
    {
        return $this->reader->snapshot($id);
    }

    public function page(int $id, int $before, string $search, callable $label): array
    {
        $event = $this->reader->event($id);
        if (!$event) {
            throw new InvalidArgumentException('El evento no existe.');
        }
        $rows = $this->reader->history($id, $before, mb_substr(trim($search), 0, 150));
        $more = count($rows) > 25;
        $rows = array_slice($rows, 0, 25);
        foreach ($rows as &$row) {
            $row['label'] = $label((string) $row['action']);
            $row['detail'] = json_decode((string) $row['payload'], true) ?? [
                'detalle' => (string) $row['payload'],
            ];
            unset($row['payload']);
        }
        unset($row);
        return [
            'ok' => true,
            'event' => $event,
            'rows' => $rows,
            'next_cursor' => $more ? (int) end($rows)['id'] : null,
        ];
    }
}
