<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\CardCatalog;

use InvalidArgumentException;

/** Validated native search and its stable provider/cache parameters. */
final readonly class CardSearch
{
    public array $parameters;
    public function __construct(string $query, string $format, public int $page)
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            throw new InvalidArgumentException('Escribe entre 2 y 100 caracteres para buscar.');
        }
        if (
            !in_array($format, ['all', 'tcg', 'goat', 'rush duel'], true) ||
            $page < 0 ||
            $page > 1000
        ) {
            throw new InvalidArgumentException('Filtro de búsqueda inválido.');
        }
        $params = ['num' => 20, 'offset' => $page * 20, 'sort' => 'name'];
        $params[ctype_digit($query) ? 'id' : 'fname'] = $query;
        if ($format !== 'all') {
            $params['format'] = $format;
        }
        $this->parameters = $params;
    }

    public function cacheKey(): string
    {
        return 'search-' .
            hash('sha256', 'pagination-v2:' . json_encode($this->parameters)) .
            '.json';
    }
}
