<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\CardCatalog;

use StationX\Yugioh\Application\CardCatalog\CatalogImage;

/** Native JSON or image response; the emitter is the only place that writes HTTP output. */
final readonly class CatalogResponse
{
    public function __construct(
        public int $status,
        public ?array $body = null,
        public ?CatalogImage $image = null,
    ) {}
    public function emit(): void
    {
        http_response_code($this->status);
        if ($this->image) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=2592000, immutable');
            header('X-Content-Type-Options: nosniff');
            readfile($this->image->path);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(
            $this->body,
            JSON_UNESCAPED_UNICODE | ($this->status === 200 ? JSON_THROW_ON_ERROR : 0),
        );
    }
}
