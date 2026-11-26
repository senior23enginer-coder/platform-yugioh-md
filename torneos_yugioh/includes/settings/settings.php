<?php
declare(strict_types=1);

/**
 * settings: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_can_edit(): bool
{
    return true;
}

function ygo_default_settings(): array
{
    return [
        'allow_early_results' => '0',
        'allow_overdue_results' => '1',
        'result_date_guard' => '1',
        'alert_days_ahead' => '3',
        'valid_weekdays' => '1,2,3,4,5,6,0',
        'allowed_start_time' => '10:00',
        'allowed_end_time' => '22:00',
        'max_matches_per_day' => '3',
        'allow_special_days' => '0',
        'special_days' => '',
        'venues' => 'Station X Tulua',
        'stats_minimum_sample' => '3',
        'tier_sample_minimum' => '3',
        'best_debut_min_matches' => '3',
        'meta_presence_weight' => '50',
        'tier_zero_presence' => '35',
        'tier_zero_winrate' => '65',
        'tier_one_score' => '70',
        'tier_two_score' => '45',
        'elo_k_factor' => '32',
        'live_refresh_seconds' => '15',
        'notification_minutes_before' => '30',
    ];
}

function ygo_default_ui_labels(): array
{
    return [
        ['route.dashboard', 'Inicio / Dashboard', 'Temporada editable y estado general de la plataforma.'],
        ['route.buscar', 'Buscar', 'Busca decks, equipos, eventos, rondas y pantallas.'],
        ['route.clasificatorio', 'Clasificatorio', 'Fase previa con duelos, ranking y programacion oficial.'],
        ['route.divisiones', 'Divisiones', 'CRUD de divisiones y agrupacion de decks.'],
        ['route.calendario', 'Calendario', 'Agenda de eventos, jornadas y combates programados.'],
        ['route.resultados', 'Resultados', 'Registro validado de resultados por reglas persistidas.'],
        ['route.tabla', 'Tablas', 'Posiciones calculadas desde resultados reales.'],
        ['route.deck', 'Decks', 'Listado y filtros de decks guardados. La ficha se abre en una pantalla aparte.'],
        ['route.deck_ficha', 'Ficha del deck', 'Detalle, estadisticas, cartas, eventos y edicion del deck seleccionado.'],
        ['route.equipos', 'Equipos', 'Listado y filtros de equipos guardados. La ficha se abre en una pantalla aparte.'],
        ['route.equipo_ficha', 'Ficha del equipo', 'Crear, editar, asociar decks sin equipo y quitar decks asociados.'],
        ['route.estadisticas', 'Estadisticas y tops', 'Lectura competitiva calculada desde eventos, decks, equipos y resultados.'],
        ['route.torneos', 'Eventos competitivos', 'Tabla general de torneos, copas, ligas, finales y clasificatorios.'],
        ['route.crear_evento', 'Crear evento', 'Ficha para crear o editar eventos competitivos.'],
        ['route.copas', 'Copas', 'Gestion y seguimiento de copas y llaves.'],
        ['route.mundial', 'Mundial', 'Vista de mundial, grupos y eliminatorias.'],
        ['route.herramienta_lp', 'Calculadora LP', 'Herramienta de puntos de vida para duelos estilo YUGIOH Neuron.'],
        ['route.reglamento', 'Reglas custom', 'CRUD de reglas y condiciones que valida la plataforma al registrar resultados.'],
        ['route.reglas', 'Reglas', 'Administracion de reglas custom.'],
        ['route.gestion', 'Configuracion', 'Administracion central de eventos, formatos, reglas, calendario, resultados, decks y equipos.'],
        ['gestion.command_title', 'Centro de mando', 'Gestiona el flujo completo: clasificatorio vivo, divisiones oficiales, calendario y resultados.'],
        ['gestion.settings_title', 'Reglas de ejecucion de duelos', 'Controla si se pueden registrar resultados fuera de la fecha programada. Se guarda en MySQL por temporada.'],
        ['gestion.labels_title', 'Titulos y opciones dinamicas', 'Edita textos visibles y catálogos de opciones persistidos en base de datos.'],
    ];
}

function ygo_settings(PDO $pdo): array
{
    return \StationX\Yugioh\Infrastructure\Composition\SettingsModule::read($pdo)->settings(
        ygo_current_season_id($pdo),
        ygo_default_settings(),
    );
}

function ygo_save_setting(PDO $pdo, string $key, string $value): void
{
    \StationX\Yugioh\Infrastructure\Composition\SettingsModule::writer($pdo)->save(
        ygo_current_season_id($pdo),
        $key,
        $value,
    );
}

function ygo_ensure_default_settings(PDO $pdo): void
{
    \StationX\Yugioh\Infrastructure\Composition\SettingsModule::writer($pdo)->ensureSettings(
        ygo_current_season_id($pdo),
        ygo_default_settings(),
    );
}

function ygo_ensure_default_ui_labels(PDO $pdo): void
{
    \StationX\Yugioh\Infrastructure\Composition\SettingsModule::writer($pdo)->ensureLabels(
        ygo_current_season_id($pdo),
        ygo_default_ui_labels(),
    );
}

function ygo_ui_labels(PDO $pdo): array
{
    return \StationX\Yugioh\Infrastructure\Composition\SettingsModule::read($pdo)->labels(
        ygo_current_season_id($pdo),
        ygo_default_ui_labels(),
    );
}

function ygo_setting_enabled(array $settings, string $key): bool
{
    return in_array((string) ($settings[$key] ?? '0'), ['1', 'true', 'yes', 'on'], true);
}
