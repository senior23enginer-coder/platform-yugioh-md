<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final class PostRedirectTarget
{
    public static function resolve(string $posted, array $input): array
    {
        $screen = match (true) {
            preg_match('/^jugador_\d+$/', $posted) === 1 => 'estadisticas',
            preg_match('/^(?:deck|deck_analysis|logo_deck|matchup_deck)_\d+$/', $posted) === 1
                => 'deck_ficha',
            preg_match('/^(?:equipo|logo_team)_\d+$/', $posted) === 1 => 'equipo_ficha',
            preg_match('/^logo_competition_\d+$/', $posted) === 1 => 'evento_ficha',
            str_starts_with($posted, 'clasificatorio_') => 'clasificatorio',
            in_array(
                $posted,
                [
                    'resultado',
                    'resultado_bloqueado_fecha',
                    'resultado_bloqueado_inicio',
                    'resultado_bloqueado_rendicion',
                ],
                true,
            )
                => 'resultados',
            in_array(
                $posted,
                [
                    'calendario',
                    'calendario_reprogramado',
                    'calendario_bloqueado_finalizado',
                    'partido_manual',
                    'partido_invalido',
                    'amistoso_eliminado',
                    'amistoso_no_eliminado',
                    'calendario_bloqueado_regla',
                    'partido_bloqueado_regla',
                    'partido_bloqueado_pool',
                ],
                true,
            )
                => 'calendario',
            $posted === 'reglamento' => 'reglamento',
            $posted === 'reglas' => 'reglas',
            $posted === 'evento' ||
                str_starts_with($posted, 'evento_configurado_') ||
                preg_match(
                    '/^(evento_avanzado|divisiones_aplicadas|movimientos_liga|ranking_recalculado)_/',
                    $posted,
                ) === 1
                => 'evento_ficha',
            default => 'gestion',
        };

        $eventId = 0;
        if (preg_match('/^(?:evento_configurado|logo_competition)_(\d+)$/', $posted, $match)) {
            $eventId = (int) $match[1];
        } elseif (
            preg_match(
                '/^(?:evento_avanzado|divisiones_aplicadas|movimientos_liga|ranking_recalculado)_(\d+)_\d+$/',
                $posted,
                $match,
            )
        ) {
            $eventId = (int) $match[1];
        } elseif ($screen === 'clasificatorio') {
            $eventId = (int) ($input['qualifier_competition_id'] ?? 0);
        }
        $query = $eventId > 0 ? '&event_id=' . $eventId : '';
        if (
            preg_match('/^(?:deck|deck_analysis|logo_deck|matchup_deck)_(\d+)$/', $posted, $match)
        ) {
            $query .= '&deck_id=' . (int) $match[1];
            if (
                str_starts_with($posted, 'matchup_deck_') ||
                str_starts_with($posted, 'deck_analysis_')
            ) {
                $query .= '&deck_view=analysis';
            }
        } elseif (preg_match('/^(?:equipo|logo_team)_(\d+)$/', $posted, $match)) {
            $query .= '&team_id=' . (int) $match[1];
        }
        if (str_starts_with($posted, 'temporada_')) {
            $screen = 'temporadas';
        }
        if ($posted === 'evento_temporada_bloqueada') {
            $screen = 'torneos';
        }
        return ['screen' => $screen, 'extraQuery' => $query];
    }
}
