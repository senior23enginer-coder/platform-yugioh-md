<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\News;

use StationX\Yugioh\Domain\News\NewsFilter;

/** Native twelve-item paging and projections using a replaceable read port. */
final readonly class ListNews
{
    public function __construct(private NewsReadStore $store, private NewsProviders $providers) {}
    public function execute(array $input): array
    {
        $sources = $this->providers->all();
        $filter = NewsFilter::fromArray($input, $sources);
        $total = $this->store->count($filter);
        $pages = max(1, (int) ceil($total / 12));
        $page = max(1, min($pages, $filter->page));
        $rows = $this->store->items($filter, $page);
        $decks = $this->store->activeDecks();
        $items = array_map(static fn($item) => NewsItemView::present($item, $decks), $rows);
        $states = $this->store->sourceStates();
        foreach ($states as &$state) {
            $key = $state['source_key'];
            $state = array_merge($sources[$key], $state);
            $state['count'] = $this->store->sourceCount($key);
        }
        unset($state);
        return [
            'items' => $items,
            'sources' => $states,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ] +
            $this->store->counts() + ['timezone' => 'America/Bogota', 'refresh_minutes' => 30];
    }
}
