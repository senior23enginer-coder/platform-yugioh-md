<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

/** Pure multiplayer state transitions. Writes and sporting identity capture belong to adapters. */
final class MultiplayerMatch
{
    public static function begin(array $ids, int $lp = 8000, bool $started = true): array
    {
        if (count($ids) < 3 || count($ids) > 4 || count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException(
                'Battle Royale requiere 3 o 4 jugadores con decks diferentes.',
            );
        }
        if ($lp < 1 || $lp > 1000000) {
            throw new InvalidArgumentException('LP inicial fuera de rango.');
        }
        $players = [];
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Participante invalido.');
            }
            $players[] = [
                'deck_id' => $id,
                'lp' => $lp,
                'turns' => 0,
                'eliminated' => false,
                'reason' => null,
            ];
        }
        $players[0]['turns'] = $started ? 1 : 0;
        return [
            'version' => 'STATION_X_FFA_V1',
            'players' => $players,
            'current' => 0,
            'turn' => $started ? 1 : 0,
            'phase' => $started ? 'DRAW' : 'WAITING',
            'status' => $started ? 'EN CURSO' : 'PENDIENTE',
            'winner' => null,
            'revision' => 0,
        ];
    }

    public static function apply(array $s, array $command): array
    {
        if ($s['status'] === 'FINALIZADA') {
            throw new InvalidArgumentException(
                'El duelo ya tiene un unico superviviente y esta cerrado.',
            );
        }
        $kind = $command['kind'] ?? '';
        $before = $s['current'];
        if ($s['status'] === 'PENDIENTE') {
            if ($kind !== 'start') {
                throw new InvalidArgumentException(
                    'Inicia la mesa antes de registrar sus acciones.',
                );
            }
            $s['status'] = 'EN CURSO';
            $s['turn'] = 1;
            $s['phase'] = 'DRAW';
            $s['players'][0]['turns'] = 1;
            $s['revision']++;
            return $s;
        }
        if ($kind === 'next_phase') {
            $phases = ['DRAW', 'STANDBY', 'MAIN1', 'BATTLE', 'MAIN2', 'END'];
            $at = array_search($s['phase'], $phases, true);
            if ($at === 5) {
                $kind = 'next_turn';
            } else {
                $s['phase'] = $phases[$at + 1];
            }
        }
        if (in_array($kind, ['lp', 'eliminate'], true)) {
            $idx = null;
            foreach ($s['players'] as $i => $p) {
                if ($p['deck_id'] === ($command['deck_id'] ?? null)) {
                    $idx = $i;
                }
            }
            if ($idx === null || $s['players'][$idx]['eliminated']) {
                throw new InvalidArgumentException(
                    'El jugador no pertenece al duelo o ya fue eliminado.',
                );
            }
            if ($kind === 'lp') {
                $delta = $command['delta'] ?? null;
                if (!is_int($delta) || $delta === 0 || abs($delta) > 1000000) {
                    throw new InvalidArgumentException('Cambio de LP invalido.');
                }
                $lp = $s['players'][$idx]['lp'] + $delta;
                if ($lp < 0 || $lp > 1000000) {
                    throw new InvalidArgumentException('Los LP deben quedar entre 0 y 1000000.');
                }
                $s['players'][$idx]['lp'] = $lp;
                if ($lp === 0) {
                    $s['players'][$idx]['eliminated'] = true;
                    $s['players'][$idx]['reason'] = 'life_points';
                }
            } else {
                $reason = $command['reason'] ?? '';
                if (!in_array($reason, ['surrender', 'deck_out', 'effect'], true)) {
                    throw new InvalidArgumentException('Indica rendicion, deck agotado o efecto.');
                }
                $s['players'][$idx]['eliminated'] = true;
                $s['players'][$idx]['reason'] = $reason;
            }
        } elseif (!in_array($kind, ['next_turn', 'next_phase'], true)) {
            throw new InvalidArgumentException('Accion de duelo desconocida.');
        }
        $alive = array_keys(array_filter($s['players'], static fn($p) => !$p['eliminated']));
        if (count($alive) === 1) {
            $s['status'] = 'FINALIZADA';
            $s['winner'] = $s['players'][$alive[0]]['deck_id'];
            $s['phase'] = 'FINISHED';
        } elseif ($kind === 'next_turn' || $s['players'][$before]['eliminated']) {
            do {
                $s['current'] = ($s['current'] + 1) % count($s['players']);
            } while ($s['players'][$s['current']]['eliminated']);
            $s['turn']++;
            $s['players'][$s['current']]['turns']++;
            $s['phase'] = 'DRAW';
        }
        $s['revision']++;
        return $s;
    }
}
