<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\Decklists;

/** Existing decklist JSON serialization contract; native capture retains emission-error handling. */
final readonly class DecklistResponse
{
    public function __construct(public int $status, public array $body) {}
    public function emit(): void
    {
        http_response_code($this->status);
        echo json_encode(
            $this->body,
            JSON_UNESCAPED_UNICODE | ($this->status === 200 ? JSON_THROW_ON_ERROR : 0),
        );
    }
}
