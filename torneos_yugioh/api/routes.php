<?php
declare(strict_types=1);

/** HTTP aliases only. Each route delegates to a native controller; no SQL or business rules here. */
return [
    '/decklist' => ['api' => 'deck_builder', 'methods' => ['GET', 'POST'], 'query' => ['deck_id']],
    '/cards/search' => ['api' => 'deck_builder', 'op' => 'search', 'methods' => ['GET'], 'query' => ['q', 'format', 'page']],
    '/cards/detail' => ['api' => 'deck_builder', 'op' => 'detail', 'methods' => ['GET'], 'query' => ['id', 'local']],
    '/cards/image' => ['api' => 'deck_builder', 'op' => 'image', 'methods' => ['GET'], 'query' => ['id', 'local', 'size']],
    '/combos' => ['api' => 'combo_creator', 'methods' => ['GET', 'POST'], 'query' => ['deck_id', 'history']],
    '/worlds' => ['api' => 'deck_attributes', 'methods' => ['GET', 'POST'], 'query' => ['deck_id']],
    '/deck-data/status' => ['api' => 'deck_data', 'op' => 'status', 'methods' => ['GET'], 'query' => ['deck_id']],
    '/deck-data/export' => ['api' => 'deck_data', 'op' => 'export', 'methods' => ['GET'], 'query' => ['deck_id']],
    '/deck-data/preview' => ['api' => 'deck_data', 'op' => 'preview', 'methods' => ['POST'], 'query' => ['deck_id']],
    '/deck-data/commit' => ['api' => 'deck_data', 'op' => 'commit', 'methods' => ['POST'], 'query' => ['deck_id']],
];
