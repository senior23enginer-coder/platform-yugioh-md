<?php
declare(strict_types=1);

/**
 * teams: compatibility services extracted from the web entry point.
 * Preserve public functions, transaction scopes and serialized write locks.
 * See context/source-architecture.md for the current migration boundaries.
 */

function ygo_competition_defaults(string $type, string $ruleset, string $name): array
{
    $preset = [
        'qualifier' => ['suizo clasificatorio', '1v1', 5, 1, 'auto_swiss', 'all_active', 'Dos victorias consecutivas o ventaja al cierre D4/D5', 'Menor marcador al cierre del match', 'D5 empatado o dos empates consecutivos'],
        'tournament' => ['torneo competitivo', '1v1', 5, 1, 'manual', 'all_active', 'Victoria por match segun reglamento', 'Derrota por match segun reglamento', 'Empate segun reglamento'],
        'league' => ['liga todos contra todos', '1v1', 5, 1, 'auto_round_robin', 'division', 'Victoria por match segun D1-D5', 'Derrota por match segun D1-D5', 'Empate valido solo cuando el reglamento lo permita'],
        'cup' => ['eliminacion directa', '1v1', 5, 1, 'auto_knockout', 'qualified', 'Ganar el match para avanzar de ronda', 'Perder el match elimina o envia a llave secundaria', 'Empate genera desempate completo'],
        'world' => ['grupos y eliminacion directa', '1v1', 5, 1, 'manual', 'top_cut', 'Victoria suma en grupos o avanza en llave', 'Derrota suma en grupos o elimina en llave', 'Empate permitido en grupos; desempate en eliminatoria'],
        'final' => ['final unica', '1v1', 5, 1, 'manual', 'top_cut', 'Ganar la serie final', 'Perder la serie final', 'Empate fuerza desempate completo'],
        'friendly' => ['amistoso rotativo', '1v1', 5, 1, 'friendly_rotation', 'all_active', 'Resultado amistoso registrado en historial', 'Resultado amistoso registrado en historial', 'Empate amistoso permitido'],
        'special' => ['evento especial editable', '1v1', 5, 1, 'manual', 'manual', 'Condicion definida por el evento', 'Condicion definida por el evento', 'Condicion definida por el evento'],
    ][$type] ?? ['evento editable', '1v1', 5, 1, 'manual', 'manual', 'Condicion definida por el evento', 'Condicion definida por el evento', 'Condicion definida por el evento'];

    return [
        'format' => $preset[0],
        'match_format' => $preset[1],
        'best_of' => $preset[2],
        'participants_per_side' => $preset[3],
        'schedule_strategy' => $preset[4],
        'deck_pool' => $preset[5],
        'victory_condition' => $preset[6],
        'defeat_condition' => $preset[7],
        'draw_condition' => $preset[8],
        'rules' => $name . ' usa reglamento ' . strtoupper(str_replace('_', ' ', $ruleset)) . ' y reglas deportivas editables por evento.',
    ];
}

function ygo_team_name_for_deck(string $deckName, ?string $archetype = null): string
{
    return ygo_team_profile_for_deck($deckName, $archetype)['name'];
}

function ygo_team_profile_for_deck(string $deckName, ?string $archetype = null): array
{
    return \StationX\Yugioh\Domain\Teams\TeamProfile::forDeck($deckName, $archetype);
}

function ygo_find_or_create_team(PDO $pdo, string $teamName, ?array $profile = null): int
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamModule::findOrCreate($pdo)->execute($teamName, $profile);
}

function ygo_sync_deck_teams(PDO $pdo): int
{
    return \StationX\Yugioh\Infrastructure\Composition\TeamModule::sync($pdo)->execute();
}
