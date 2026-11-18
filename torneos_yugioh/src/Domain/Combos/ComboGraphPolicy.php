<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Combos;

use InvalidArgumentException;

/** Validates an acyclic visual graph against explicit inventory and format inputs. No source reads. */
final class ComboGraphPolicy
{
    public static function prepare(array $input, array $inventory): array
    {
        $id = (string) ($input['id'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $id)) {
            throw new InvalidArgumentException('Identificador de combo no válido.');
        }
        $kind = (string) ($input['kind'] ?? 'combo');
        if (!in_array($kind, ['combo', 'strategy'], true)) {
            throw new InvalidArgumentException('Tipo de guía no válido.');
        }
        $turn = (string) ($input['turn_order'] ?? 'any');
        if (!in_array($turn, ['any', 'first', 'second'], true)) {
            throw new InvalidArgumentException('Orden de turno no válido.');
        }
        $format = $input['board_format'] ?? 'unspecified';
        $status = $input['editorial_status'] ?? 'draft';
        if (
            !in_array($format, ['unspecified', 'tcg', 'rush', 'goat'], true) ||
            !in_array($status, ['draft', 'conditional', 'pending', 'not_executable'], true)
        ) {
            throw new InvalidArgumentException('Formato o estado documental no válido.');
        }
        if (
            $format !== 'unspecified' &&
            ($inventory['format'] ?? 'unspecified') !== 'unspecified' &&
            $format !== $inventory['format']
        ) {
            throw new InvalidArgumentException(
                'El tablero no corresponde al formato documentado del deck.',
            );
        }
        $nodes = $input['nodes'] ?? null;
        $edges = $input['edges'] ?? null;
        if (
            !is_array($nodes) ||
            !array_is_list($nodes) ||
            count($nodes) < 1 ||
            count($nodes) > 60 ||
            !is_array($edges) ||
            !array_is_list($edges) ||
            count($edges) > 120
        ) {
            throw new InvalidArgumentException(
                'La guía admite de 1 a 60 pasos y hasta 120 conexiones.',
            );
        }
        $allowed = array_column($inventory['cards'], null, 'id');
        $seen = [];
        $clean = [];
        foreach ($nodes as $n) {
            if (!is_array($n)) {
                throw new InvalidArgumentException('Paso no válido.');
            }
            $nid = (string) ($n['id'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/D', $nid) || isset($seen[$nid])) {
                throw new InvalidArgumentException('Identificador de paso repetido o no válido.');
            }
            $seen[$nid] = true;
            $type = (string) ($n['kind'] ?? 'step');
            if (!in_array($type, ['opening', 'step', 'decision', 'result'], true)) {
                throw new InvalidArgumentException('Tipo de paso no válido.');
            }
            foreach (['x', 'y'] as $axis) {
                if (
                    !isset($n[$axis]) ||
                    !is_numeric($n[$axis]) ||
                    !is_finite((float) $n[$axis]) ||
                    (float) $n[$axis] < 0 ||
                    (float) $n[$axis] > 5000
                ) {
                    throw new InvalidArgumentException('Posición fuera del lienzo.');
                }
            }
            $refs = $n['cards'] ?? [];
            if (!is_array($refs) || !array_is_list($refs) || count($refs) > 8) {
                throw new InvalidArgumentException('Cada paso admite hasta ocho cartas.');
            }
            $cards = [];
            $copies = [];
            foreach ($refs as $ref) {
                $cid = filter_var($ref, FILTER_VALIDATE_INT);
                if (!$cid || !isset($allowed[$cid])) {
                    throw new InvalidArgumentException(
                        'La carta no pertenece al deck seleccionado.',
                    );
                }
                $copies[$cid] = ($copies[$cid] ?? 0) + 1;
                if ($copies[$cid] > (int) $allowed[$cid]['quantity']) {
                    throw new InvalidArgumentException(
                        'El paso usa más copias de una carta que la lista cargada.',
                    );
                }
                if ($type === 'opening' && $allowed[$cid]['section'] !== 'main') {
                    throw new InvalidArgumentException(
                        'La mano inicial solo admite cartas del Main Deck.',
                    );
                }
                $cards[] = $cid;
            }
            $clean[] =
                [
                    'id' => $nid,
                    'kind' => $type,
                    'title' => ComboStepPolicy::text($n['title'] ?? '', 120, true),
                    'action' => ComboStepPolicy::text($n['action'] ?? '', 80),
                    'explanation' => ComboStepPolicy::text($n['explanation'] ?? '', 4000),
                    'x' => round((float) $n['x'], 1),
                    'y' => round((float) $n['y'], 1),
                    'cards' => $cards,
                ] + ComboStepPolicy::details($n, $cards, $format);
        }
        $links = [];
        $pairs = [];
        $degree = array_fill_keys(array_keys($seen), 0);
        $out = [];
        foreach ($edges as $edge) {
            if (!is_array($edge)) {
                throw new InvalidArgumentException('Conexión no válida.');
            }
            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');
            $key = $from . ':' . $to;
            if (!isset($seen[$from], $seen[$to]) || $from === $to || isset($pairs[$key])) {
                throw new InvalidArgumentException(
                    'Conexión repetida, circular o sin un paso válido.',
                );
            }
            $pairs[$key] = true;
            $degree[$to]++;
            $out[$from][] = $to;
            $links[] = [
                'from' => $from,
                'to' => $to,
                'label' => ComboStepPolicy::text($edge['label'] ?? '', 160),
            ];
        }
        $queue = array_keys(array_filter($degree, static fn($n) => $n === 0));
        $visited = 0;
        while ($queue) {
            $from = array_shift($queue);
            $visited++;
            foreach ($out[$from] ?? [] as $to) {
                if (--$degree[$to] === 0) {
                    $queue[] = $to;
                }
            }
        }
        if ($visited !== count($clean)) {
            throw new InvalidArgumentException(
                'La conexión crea un ciclo. Usa un paso nuevo para explicar una continuación.',
            );
        }
        $optional = [];
        if (isset($input['board_format'])) {
            $optional['board_format'] = $format;
        }
        if (isset($input['editorial_status'])) {
            $optional['editorial_status'] = $status;
        }
        if (!empty($input['skill_id'])) {
            $skill = null;
            foreach ($inventory['skills'] ?? [] as $item) {
                if ($item['id'] === $input['skill_id']) {
                    $skill = $item;
                }
            }
            if (!$skill || $format !== 'rush') {
                throw new InvalidArgumentException(
                    'La Skill no pertenece al deck o al formato Rush.',
                );
            }
            $optional['skill_id'] = $skill['id'];
            $optional['skill_snapshot'] = $skill;
        }
        return [
            'schema_version' => 1,
            'id' => $id,
            'deck_id' => (int) $inventory['deck']['id'],
            'kind' => $kind,
            'title' => ComboStepPolicy::text($input['title'] ?? '', 160, true),
            'objective' => ComboStepPolicy::text($input['objective'] ?? '', 4000),
            'conditions' => ComboStepPolicy::text($input['conditions'] ?? '', 6000),
            'turn_order' => $turn,
            'nodes' => $clean,
            'edges' => $links,
        ] + $optional;
    }
}
