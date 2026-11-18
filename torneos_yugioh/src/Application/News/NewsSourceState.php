<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

/** Due-source selection and backoff use the same native UTC clock as persistence. */
interface NewsSourceState
{
    public function nextDue(bool $retryFailed): ?array;
    public function attempted(string $source): void;
    public function succeeded(string $source, int $imported): void;
    public function failed(string $source, string $message): void;
}
