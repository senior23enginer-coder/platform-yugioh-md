<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use InvalidArgumentException;

final class MarkdownStudy
{
    public static function sections(string $raw): array
    {
        preg_match_all('/^(#{2,4}) (.*?)\R(.*?)(?=^#{1,4} |\z)/msu', $raw, $m, PREG_SET_ORDER);
        return $m;
    }

    public static function plain(string $s): string
    {
        return trim(str_replace(['**', '`'], '', $s));
    }

    public static function photos(string $text, array $cards): array
    {
        $photos = [];
        foreach ($cards as $c) {
            $names = array_unique(array_merge([$c['name']], $c['source_aliases'] ?? []));
            $found = false;
            foreach ($names as $n) {
                if (
                    mb_strlen($n) > 3 &&
                    preg_match('~(?<![\pL\pN])' . preg_quote($n, '~') . '(?![\pL\pN])~iu', $text)
                ) {
                    $found = true;
                }
            }
            if (
                !empty($c['cid']) &&
                preg_match('~(?:cid=|CID\s*|#)' . (int) $c['cid'] . '\b~i', $text)
            ) {
                $found = true;
            }
            if ($found) {
                $photos[] = array_intersect_key(
                    $c,
                    array_flip(['cid', 'name', 'image', 'full_image']),
                ) + ['label' => 'Carta mencionada en el paso'];
            }
        }
        return $photos;
    }

    public static function parse(string $raw, array $base, int $deckId): array
    {
        $data = CalculatorContract::extract($raw, $base, $deckId);
        $raw = CalculatorContract::withoutContract($raw);
        $by = [];
        foreach ($data['routes'] ?? [] as $r) {
            $by[$r['id']] = $r;
        }
        $parsed = 0;
        foreach (MarkdownStudy::sections($raw) as $part) {
            [, , $title, $body] = $part;
            $rid = null;
            if (preg_match('/`(' . $deckId . ':A09:[^`]+)`/u', $title, $m)) {
                $rid = $m[1];
            } elseif (preg_match('/^(?:MEMD-)?((?:[PSG]-)?[CV]\d{2})\b/u', $title, $m)) {
                $rid = $deckId . ':A09:' . $m[1];
            }
            if ($rid && preg_match('/^1\.\s/m', $body)) {
                preg_match_all(
                    '/^(\d+)\.\s+(.*?)(?=^\d+\.\s|^#{1,4} |\n\n|\z)/msu',
                    $body,
                    $matches,
                    PREG_SET_ORDER,
                );
                $steps = [];
                foreach ($matches as $m) {
                    if ((int) $m[1] !== count($steps) + 1) {
                        throw new InvalidArgumentException('Numeración incompleta en ' . $rid);
                    }
                    $text = MarkdownStudy::plain($m[2]);
                    $steps[] = [
                        'number' => (int) $m[1],
                        'action' => $text,
                        'detail' => $text,
                        'photos' => MarkdownStudy::photos($text, $data['cards'] ?? []),
                        'requirements' => [],
                        'resources' => [],
                        'board' => [],
                    ];
                }
                $r = $by[$rid] ?? [
                    'id' => $rid,
                    'name' => MarkdownStudy::plain($title),
                    'title' => MarkdownStudy::plain($title),
                    'family' => 'Desarrollo de la jugada',
                    'purpose' => '',
                    'category' => 'APERTURA',
                ];
                $old = $r['steps'] ?? [];
                foreach ($steps as $i => &$step) {
                    if (($old[$i]['action'] ?? null) === $step['action']) {
                        $step = $old[$i];
                    }
                }
                unset($step);
                if (array_column($old, 'action') !== array_column($steps, 'action')) {
                    unset($r['endboard']);
                    $r['board'] = [];
                    $r['resources'] = [];
                    $r['result'] =
                        'Estado final pendiente de especificar en la secuencia actualizada.';
                }
                preg_match('/^1\.\s/m', $body, $first, PREG_OFFSET_CAPTURE);
                $opening = MarkdownStudy::plain(substr($body, 0, $first[0][1]));
                $r['steps'] = $steps;
                $r['opening'] = $opening;
                $r['initial_details'] = $opening;
                $r['hand_photos'] = MarkdownStudy::photos($opening, $data['cards'] ?? []);
                $r['technical'] = MarkdownStudy::plain($body);
                $r['documentary_source'] = $body;
                $r['editorial_status'] = 'pending';
                $r['notice'] =
                    'Secuencia documental importada; no certificada mediante simulación.';
                foreach (
                    ['native_editor_document', 'native_editor_cards', 'native_editor_revision']
                    as $key
                ) {
                    unset($r[$key]);
                }
                if (
                    preg_match('/^- \*\*(?:Objetivo|Propósito)[^\n]*?\*\*[: ]*(.+)$/mu', $body, $m)
                ) {
                    $r['purpose'] = MarkdownStudy::plain($m[1]);
                }
                if (
                    preg_match(
                        '/\*\*(?:Campo final|Tablero final|Final|Resultado|Salida)[^\n]*?(?:\R.*?)(?=\n\n|\z)/su',
                        $body,
                        $m,
                    ) ||
                    preg_match(
                        '/\*\*(?:Campo final|Tablero final|Final|Resultado|Salida)[^\n]*/u',
                        $body,
                        $m,
                    )
                ) {
                    $result = MarkdownStudy::plain($m[0]);
                    if ($result !== ($r['result'] ?? '')) {
                        unset($r['endboard']);
                        $r['board'] = [];
                        $r['resources'] = [];
                    }
                    $r['result'] = $result;
                }
                $r += [
                    'result' => 'Estado final no especificado.',
                    'board' => [],
                    'resources' => [],
                ];
                $by[$rid] = $r;
                $parsed++;
            }
            if (preg_match('/^(?:\d+\.\s+)(.+)$/u', $title, $m)) {
                foreach ($data['cards'] ?? [] as $i => $card) {
                    if (mb_strtolower(trim($m[1])) === mb_strtolower($card['name'])) {
                        preg_match_all(
                            '/^- \*\*(.*?)\*\*[:：]?\s*(.*?)(?=^- \*\*|\n\n|\z)/msu',
                            $body,
                            $fs,
                            PREG_SET_ORDER,
                        );
                        foreach ($fs as $field) {
                            $label = rtrim(MarkdownStudy::plain($field[1]), ':');
                            $text = MarkdownStudy::plain($field[2]);
                            $found = false;
                            foreach ($data['cards'][$i]['fields'] as &$f) {
                                if ($f['title'] === $label) {
                                    $f['text'] = $text;
                                    $found = true;
                                }
                            }
                            unset($f);
                            if (!$found) {
                                $data['cards'][$i]['fields'][] = [
                                    'title' => $label,
                                    'text' => $text,
                                ];
                            }
                        }
                    }
                }
            }
        }
        if (!$parsed && empty($base['routes'])) {
            $data['routes'] = [];
        } else {
            $data['routes'] = array_values($by);
        }
        $data['imported_source_text'] = $raw;
        return $data;
    }
}
