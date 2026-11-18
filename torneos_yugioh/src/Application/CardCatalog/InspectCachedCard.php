<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

use RuntimeException;
use StationX\Yugioh\Domain\CardCatalog\CardIdentity;

/** Strictly local inspector: absent text/image remains absent; never triggers a provider request. */
final readonly class InspectCachedCard
{
    public function __construct(private CatalogCache $cache, private CatalogImages $images) {}
    public function execute(int $id): ?array
    {
        CardIdentity::requireValid($id);
        $body = $this->cache->read('card-' . $id . '.json');
        if ($body === null) {
            return null;
        }
        $card = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($card) || (int) ($card['id'] ?? 0) !== $id) {
            throw new RuntimeException('La identidad del catálogo no coincide.');
        }
        $card['image'] = $this->images->hasFull($id)
            ? './?api=deck_builder&op=image&size=full&local=1&id=' . $id
            : null;
        $card['text_source'] = 'YGOPRODeck';
        $card['text_language'] = 'en';
        return $card;
    }
}
