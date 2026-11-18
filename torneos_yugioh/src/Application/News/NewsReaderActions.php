<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

interface NewsReaderActions
{
    public function read(int $id, bool $value): void;
    public function bookmark(int $id, bool $value): void;
    public function readAll(): void;
}
