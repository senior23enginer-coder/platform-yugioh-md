<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use InvalidArgumentException;

/** Reading/bookmarking changes only operator state, never editorial content or sporting data. */
final readonly class UpdateNewsReading
{
    public function __construct(private NewsReaderActions $store) {}
    public function execute(string $operation, array $input): array
    {
        if ($operation === 'read_all') {
            $this->store->readAll();
            return ['updated' => true];
        }
        if (!in_array($operation, ['read', 'bookmark'], true)) {
            throw new InvalidArgumentException('Operación no válida.');
        }
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            throw new InvalidArgumentException('Publicación no válida.');
        }
        $value = (bool) ($input['value'] ?? true);
        if ($operation === 'read') {
            $this->store->read($id, $value);
        } else {
            $this->store->bookmark($id, $value);
        }
        return ['id' => $id, 'value' => $value];
    }
}
