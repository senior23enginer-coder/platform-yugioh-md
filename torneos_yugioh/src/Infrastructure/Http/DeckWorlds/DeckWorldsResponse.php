<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\DeckWorlds;

use Closure;
use DomainException;
use InvalidArgumentException;
use JsonException;
use Throwable;

/** JSON response mapping and emission belong to the incoming HTTP adapter. */
final readonly class DeckWorldsResponse
{
    public function __construct(
        public int $status,
        public array $body,
        public array $headers = [],
    ) {}

    /** @param Closure(string):void $log Internal failures are logged without leaking database details. */
    public static function failure(Throwable $error, Closure $log): self
    {
        $invalid = $error instanceof InvalidArgumentException || $error instanceof JsonException;
        $conflict = $error instanceof DomainException;
        if (!$invalid && !$conflict) {
            $log($error->getMessage());
        }
        return new self($conflict ? 409 : ($invalid ? 422 : 503), [
            'ok' => false,
            'message' =>
                $invalid || $conflict
                    ? $error->getMessage()
                    : 'No se pudieron consultar o guardar los mundos. Tu selección permanece abierta.',
        ]);
    }

    public function emit(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode(
            $this->body,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
    }
}
