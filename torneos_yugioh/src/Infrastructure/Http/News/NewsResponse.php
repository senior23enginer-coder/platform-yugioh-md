<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\News;

use StationX\Yugioh\Application\News\NewsImageFile;

/** The only news response emitter: JSON status mapping or a catalogued cache file. */
final readonly class NewsResponse
{
    public function __construct(
        public int $status,
        public ?array $body = null,
        public array $headers = [],
        public ?NewsImageFile $image = null,
    ) {}
    public static function image(?NewsImageFile $file): self
    {
        return $file
            ? new self(
                200,
                null,
                [
                    'Content-Type' => $file->mime,
                    'Cache-Control' => 'public, max-age=86400',
                    'X-Content-Type-Options' => 'nosniff',
                ],
                $file,
            )
            : new self(404);
    }

    public function emit(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->image) {
            readfile($this->image->path);
            return;
        }
        if ($this->body !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                $this->body,
                JSON_UNESCAPED_UNICODE |
                    ($this->status === 200
                        ? JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
                        : 0),
            );
        }
    }
}
