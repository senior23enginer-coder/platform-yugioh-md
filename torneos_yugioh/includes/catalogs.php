<?php
declare(strict_types=1);

function ygo_default_catalog_groups(): array
{
    return [
        'competition_type' => [
            ['code' => 'qualifier', 'label' => 'Clasificatorio', 'icon' => 'bi-funnel', 'accent' => '#00ff9c', 'sort_order' => 10],
            ['code' => 'tournament', 'label' => 'Torneo', 'icon' => 'bi-trophy', 'accent' => '#ffd400', 'sort_order' => 20],
            ['code' => 'league', 'label' => 'Liga', 'icon' => 'bi-calendar2-range', 'accent' => '#00d9ff', 'sort_order' => 30],
            ['code' => 'cup', 'label' => 'Copa', 'icon' => 'bi-award', 'accent' => '#ff2bd6', 'sort_order' => 40],
            ['code' => 'world', 'label' => 'Mundial', 'icon' => 'bi-globe-americas', 'accent' => '#9b5cff', 'sort_order' => 50],
            ['code' => 'final', 'label' => 'Final', 'icon' => 'bi-stars', 'accent' => '#ff315a', 'sort_order' => 60],
            ['code' => 'friendly', 'label' => 'Amistoso', 'icon' => 'bi-people', 'accent' => '#20f7d2', 'sort_order' => 70],
            ['code' => 'special', 'label' => 'Evento especial', 'icon' => 'bi-lightning-charge', 'accent' => '#ff8a00', 'sort_order' => 80],
        ],
        'ruleset' => [
            ['code' => 'master_duel', 'label' => 'Master Duel', 'sort_order' => 10],
            ['code' => 'tcg_advanced', 'label' => 'TCG Avanzado', 'sort_order' => 20],
            ['code' => 'tcg_traditional', 'label' => 'TCG Tradicional', 'sort_order' => 25],
            ['code' => 'ocg', 'label' => 'OCG', 'sort_order' => 30],
            ['code' => 'goat', 'label' => 'GOAT', 'sort_order' => 40],
            ['code' => 'edison', 'label' => 'Edison', 'sort_order' => 50],
            ['code' => 'rush_duel', 'label' => 'Rush Duel', 'sort_order' => 60],
            ['code' => 'duel_links', 'label' => 'Duel Links', 'sort_order' => 70],
            ['code' => 'speed_duel', 'label' => 'Speed Duel', 'sort_order' => 80],
            ['code' => 'genesis', 'label' => 'Genesis', 'sort_order' => 90],
            ['code' => 'custom', 'label' => 'Personalizado', 'sort_order' => 100],
        ],
        'deck_pool' => [
            ['code' => 'all_active', 'label' => 'Seleccionar decks del formato', 'sort_order' => 10],
            ['code' => 'division', 'label' => 'Todos los decks del formato', 'sort_order' => 20],
            ['code' => 'qualified', 'label' => 'Clasificados por evento previo', 'sort_order' => 30],
            ['code' => 'top_cut', 'label' => 'Top cut / cupos definidos', 'sort_order' => 40],
            ['code' => 'manual', 'label' => 'Seleccion manual por combate', 'sort_order' => 50],
        ],
        'competition_status' => [
            ['code' => 'pending_configuration', 'label' => 'Pendiente configuracion', 'accent' => '#ffd36a', 'sort_order' => 10],
            ['code' => 'pending_event', 'label' => 'Pendiente evento', 'accent' => '#ff9f43', 'sort_order' => 20],
            ['code' => 'active', 'label' => 'Activo', 'accent' => '#00d9ff', 'sort_order' => 30],
            ['code' => 'completed', 'label' => 'Finalizado', 'accent' => '#36f59f', 'sort_order' => 40],
            ['code' => 'closed', 'label' => 'Cerrado por temporada', 'accent' => '#94a3b8', 'sort_order' => 45],
            ['code' => 'cancelled', 'label' => 'Anulado', 'accent' => '#8a93a6', 'sort_order' => 50],
            ['code' => 'resume', 'label' => 'Reanudar', 'accent' => '#ff2bd6', 'sort_order' => 60],
        ],
        'match_status' => [
            ['code' => 'PENDIENTE', 'label' => 'Pendiente', 'accent' => '#ffd36a', 'sort_order' => 10],
            ['code' => 'EN CURSO', 'label' => 'En curso', 'accent' => '#00d9ff', 'sort_order' => 20],
            ['code' => 'REQUIERE D5', 'label' => 'Requiere D5', 'accent' => '#ff9f43', 'sort_order' => 30],
            ['code' => 'FINALIZADA', 'label' => 'Finalizada', 'accent' => '#36f59f', 'sort_order' => 40],
            ['code' => 'INVALID', 'label' => 'Invalida', 'accent' => '#ff315a', 'sort_order' => 50],
        ],
        'schedule_pattern' => [
            ['code' => 'consecutive', 'label' => 'Dias seguidos', 'sort_order' => 10],
            ['code' => 'every_other_day', 'label' => '1 dia de por medio', 'sort_order' => 20],
            ['code' => 'every_2_days', 'label' => '2 dias de por medio', 'sort_order' => 30],
            ['code' => 'every_3_days', 'label' => '3 dias de por medio', 'sort_order' => 40],
            ['code' => 'weekend', 'label' => 'Fin de semana', 'sort_order' => 50],
        ],

    ];
}

function ygo_catalog_items_from_defaults(string $catalogKey): array
{
    return ygo_default_catalog_groups()[$catalogKey] ?? [];
}

function ygo_catalog_label_options(array $items): array
{
    $options = [];
    foreach ($items as $item) {
        if (isset($item['active']) && (int) $item['active'] !== 1) {
            continue;
        }
        $code = (string) ($item['code'] ?? '');
        if ($code === '') {
            continue;
        }
        $options[$code] = (string) (($item['label'] ?? '') ?: $code);
    }
    return $options;
}

function ygo_catalog_competition_type_options(array $items): array
{
    $options = [];
    foreach ($items as $item) {
        if (isset($item['active']) && (int) $item['active'] !== 1) {
            continue;
        }
        $code = (string) ($item['code'] ?? '');
        if ($code === '') {
            continue;
        }
        $options[$code] = [
            'label' => (string) (($item['label'] ?? '') ?: $code),
            'icon' => (string) (($item['icon'] ?? '') ?: 'bi-stars'),
            'accent' => (string) (($item['accent'] ?? '') ?: '#36d8ff'),
        ];
    }
    return $options;
}

function ygo_ensure_default_catalogs(PDO $pdo, int $seasonId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_catalog_items
            (season_id, catalog_key, item_code, label, icon, accent, sort_order, meta_json, active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            item_code = item_code
    ");
    foreach (ygo_default_catalog_groups() as $catalogKey => $items) {
        foreach ($items as $item) {
            $stmt->execute([
                $seasonId,
                $catalogKey,
                (string) $item['code'],
                (string) $item['label'],
                (string) ($item['icon'] ?? ''),
                (string) ($item['accent'] ?? ''),
                (int) ($item['sort_order'] ?? 100),
                json_encode($item['meta'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}

function ygo_load_catalog_groups(PDO $pdo, int $seasonId): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM sx_ygo_catalog_items
        WHERE season_id IN (0, ?)
        ORDER BY catalog_key, sort_order, id
    ");
    $stmt->execute([$seasonId]);
    $catalogs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string) $row['catalog_key'] === 'report') continue;
        $meta = [];
        if (!empty($row['meta_json'])) {
            $decoded = json_decode((string) $row['meta_json'], true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        $catalogs[(string) $row['catalog_key']][] = [
            'id' => (int) $row['id'],
            'code' => (string) $row['item_code'],
            'label' => (string) $row['label'],
            'icon' => (string) ($row['icon'] ?? ''),
            'accent' => (string) ($row['accent'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 100),
            'active' => (int) ($row['active'] ?? 1),
            'meta' => $meta,
        ];
    }
    foreach (ygo_default_catalog_groups() as $catalogKey => $items) {
        if (empty($catalogs[$catalogKey])) {
            $catalogs[$catalogKey] = $items;
        }
    }
    return $catalogs;
}
