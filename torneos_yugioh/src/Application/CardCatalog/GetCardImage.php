<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

use InvalidArgumentException;
use StationX\Yugioh\Domain\CardCatalog\CardIdentity;

/** Validate physical catalog image requests before touching cache or external transport. */
final readonly class GetCardImage
{
    public function __construct(private CatalogImages $images) {}
    public function execute(int $id, string $size, bool $localOnly): CatalogImage
    {
        CardIdentity::requireValid($id, 'Código inválido.');
        if (!in_array($size, ['small', 'full'], true)) {
            throw new InvalidArgumentException('Tamaño de imagen inválido.');
        }
        return $this->images->get($id, $size, $localOnly);
    }
}
