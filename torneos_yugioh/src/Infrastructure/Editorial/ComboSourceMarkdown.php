<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Editorial;

final class ComboSourceMarkdown
{
    public static function render(array $record): string
    {
        $doc = $record['document'];
        $cards = array_column($record['cards_snapshot'], null, 'id');
        $kind = [
            'opening' => 'Estado inicial',
            'step' => 'Paso',
            'decision' => 'Decisión',
            'result' => 'Resultado',
        ];
        $out = [
            '# ' . $doc['title'],
            '',
            '- Deck ID: `' . $doc['deck_id'] . '`',
            '- Ruta de origen: `' . $doc['source_route_id'] . '`',
            '- Revisión del editor: **' . $record['revision'] . '**',
            '- Estado documental: `' . ($doc['editorial_status'] ?? 'draft') . '`',
            '',
            '## Objetivo',
            $doc['objective'],
            '',
            '## Requisitos y límites',
            $doc['conditions'],
            '',
            '## Nodos editables',
        ];
        foreach ($doc['nodes'] as $i => $node) {
            array_push(
                $out,
                '',
                '### ' . ($i + 1) . '. ' . $node['title'] . ' · ' . $kind[$node['kind']],
                '',
                $node['action'],
                '',
                $node['explanation'],
                '',
            );
            foreach ($node['cards'] as $id) {
                $out[] =
                    '- Carta: ' .
                    ($cards[$id]['card_name'] ?? 'Identidad no disponible') .
                    ' · ID nativo `' .
                    $id .
                    '` · ' .
                    ($cards[$id]['section'] ?? '');
            }
            foreach ($node['details'] ?? [] as $key => $value) {
                if ($value !== '') {
                    $out[] = '- ' . $key . ': ' . $value;
                }
            }
            foreach ($node['placements'] ?? [] as $p) {
                $id = $node['cards'][$p['card_index']];
                $out[] =
                    '- Ubicación: ' .
                    ($cards[$id]['card_name'] ?? $id) .
                    ' · ' .
                    $p['zone'] .
                    ' · ' .
                    $p['position'] .
                    ($p['materials'] !== '' ? ' · Materiales: ' . $p['materials'] : '');
            }
        }
        array_push($out, '', '## Conexiones y ramas');
        $nodes = array_column($doc['nodes'], null, 'id');
        foreach ($doc['edges'] as $edge) {
            $out[] =
                '- ' .
                $nodes[$edge['from']]['title'] .
                ' → ' .
                $nodes[$edge['to']]['title'] .
                ' · ' .
                $edge['label'];
        }
        if (isset($doc['skill_snapshot'])) {
            array_push(
                $out,
                '',
                '## Skill externa',
                json_encode(
                    $doc['skill_snapshot'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            );
        }
        array_push(
            $out,
            '',
            '## Documento nativo de esta revisión',
            '```json',
            json_encode(
                $doc,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR,
            ),
            '```',
            '',
        );
        return implode("\n", $out);
    }
}
