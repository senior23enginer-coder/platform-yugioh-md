<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;
use JsonException;

final class TeamConfigurationPolicy
{
    public static function decodePayload(string $json): array
    {
        try {
            $rows = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                'Los datos de equipos o resultados no tienen un formato JSON valido.',
            );
        }
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException(
                'Los datos de equipos o resultados deben ser una lista ordenada.',
            );
        }
        return $rows;
    }

    public static function decodeState(string $json): array
    {
        $state = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (($state['version'] ?? '') !== 'STATION_X_TEAMS_V1') {
            throw new InvalidArgumentException('Version de alineaciones desconocida.');
        }
        return $state;
    }

    public static function encodeState(array $state): string
    {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($json) > 65535) {
            throw new InvalidArgumentException(
                'La configuracion de equipos supera el limite de almacenamiento.',
            );
        }
        return $json;
    }

    public static function assertMutable(array $competition): void
    {
        if (
            !empty($competition['configuration_locked']) ||
            in_array(
                CompetitionLifecyclePolicy::normalizeStatus($competition['status']),
                ['completed', 'closed', 'cancelled', 'archived'],
                true,
            )
        ) {
            throw new InvalidArgumentException(
                'Las alineaciones estan bloqueadas por el estado del evento.',
            );
        }
    }

    public static function assertMode(array $competition, string $kind): void
    {
        if (
            ($kind === 'tag' && $competition['match_format'] !== '2v2') ||
            ($kind === 'team' && $competition['match_format'] !== '3v3')
        ) {
            throw new InvalidArgumentException(
                'La participacion no coincide con la modalidad: Tag 2v2 o equipos 3v3.',
            );
        }
    }

    public static function assertSameFormat(
        array $competition,
        string $actual,
        bool $existing,
    ): void {
        if (
            $competition['event_ruleset'] !== 'custom' &&
            $actual !== $competition['event_ruleset']
        ) {
            throw new InvalidArgumentException(
                $existing
                    ? 'El formato de las alineaciones cambio.'
                    : 'Los decks deben pertenecer al mismo formato del evento.',
            );
        }
    }

    public static function assertRoster(array $state, array $ids): array
    {
        $expected = TeamLineupPolicy::allIds($state);
        $posted = array_map('intval', $ids);
        sort($expected);
        sort($posted);
        if ($expected !== $posted) {
            throw new InvalidArgumentException(
                'Los participantes deben coincidir exactamente con las alineaciones guardadas.',
            );
        }
        return $expected;
    }

    public static function binding(array $state, int $a, int $b): array
    {
        $left = array_pad(TeamLineupPolicy::lineup($state, $a)['decks'], 3, null);
        $right = array_pad(TeamLineupPolicy::lineup($state, $b)['decks'], 3, null);
        return [
            $state['size'] . 'v' . $state['size'],
            $state['size'],
            $left[1],
            $right[1],
            $left[2],
            $right[2],
        ];
    }
}
