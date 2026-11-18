<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\CardCatalog;

use InvalidArgumentException;
use StationX\Yugioh\Domain\CardCatalog\CardIdentity;

/** Reads cached identity or imports precisely one provider card; never searches by a similar name. */
final readonly class LookupCard
{
    public function __construct(private CatalogCache $cache, private CatalogProvider $provider) {}
    public function execute(int $id): array
    {
        CardIdentity::requireValid($id);
        $key = 'card-' . $id . '.json';
        $cached = $this->cache->read($key);
        if ($cached !== null) {
            return json_decode($cached, true, 32, JSON_THROW_ON_ERROR);
        }
        $answer = json_decode(
            $this->provider->download('https://db.ygoprodeck.com/api/v7/cardinfo.php?id=' . $id),
            true,
            32,
            JSON_THROW_ON_ERROR,
        );
        $card = $answer['data'][0] ?? null;
        if (!$card || (int) $card['id'] !== $id) {
            throw new InvalidArgumentException('No se encontró esta carta en YGOPRODeck.');
        }
        $card = CardSummary::fromProvider($card);
        $this->cache->write($key, json_encode($card, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $card;
    }
}
