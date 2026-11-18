<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks;

use InvalidArgumentException;

/** Native inventory invariants; no banlist, source I/O or catalog lookup is inferred here. */
final class DecklistRules
{
    public static function version(array $cards): string
    {
        return hash('sha256', json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function rows(array $input, string $currentVersion): array
    {
        if (!hash_equals($currentVersion, (string) ($input['version'] ?? ''))) {
            throw new InvalidArgumentException(
                'La lista cambió desde que la abriste. Recárgala antes de guardar.',
            );
        }
        $rows = $input['cards'] ?? null;
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 300) {
            throw new InvalidArgumentException('Lista de cartas inválida.');
        }
        return $rows;
    }
    /** Validate before a possible external lookup; preserve native error ordering and existing annotations. */
    public static function prepare(array $old, mixed $row, bool $documented): array
    {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Carta inválida.');
        }
        $section = (string) ($row['section'] ?? '');
        $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
        $existing = (int) ($row['existing_id'] ?? 0);
        $cardId = (int) ($row['card_id'] ?? 0);
        if (
            !in_array($section, ['main', 'extra', 'side', 'reserve'], true) ||
            !$quantity ||
            $quantity < 1 ||
            $quantity > 99
        ) {
            throw new InvalidArgumentException(
                'Revisa la sección y la cantidad de cada carta (1 a 99).',
            );
        }
        if ($existing && !isset($old[$existing])) {
            throw new InvalidArgumentException('La carta no pertenece a esta lista.');
        }
        $source = CardAnnotations::cardMergeAnnotations(
            $old,
            $existing ? $old[$existing] : [],
            $cardId,
            $row['merged_ids'] ?? [],
        );
        $name = '';
        if ($documented) {
            $name = trim((string) ($row['card_name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 160) {
                throw new InvalidArgumentException('Nombre documental inválido.');
            }
        }
        return compact(
            'section',
            'quantity',
            'existing',
            'cardId',
            'source',
            'documented',
            'name',
            'row',
        );
    }
    /** Complete after exact catalog lookup, retaining documented names independently of image IDs. */
    public static function finish(array $plan, ?array $catalog, array $seen): array
    {
        [
            'section' => $section,
            'quantity' => $quantity,
            'existing' => $existing,
            'cardId' => $cardId,
            'source' => $source,
            'documented' => $documented,
            'name' => $name,
            'row' => $row,
        ] = $plan;
        if ($documented) {
            $key = $section . ':documented:' . mb_strtolower($name);
            $source = [
                'role' => mb_substr(trim((string) ($row['role'] ?? '')), 0, 80) ?: null,
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
            ];
            if (strlen((string) $source['notes']) > 4000) {
                throw new InvalidArgumentException(
                    'La anotación documental supera el tamaño permitido.',
                );
            }
        } elseif ($cardId) {
            $name = $catalog['name'];
            $key = $section . ':card:' . $cardId;
        } else {
            if (!$existing || !empty($source['ygoprodeck_id'])) {
                throw new InvalidArgumentException('Selecciona una carta del buscador.');
            }
            $name = $source['card_name'];
            $key = 'legacy:' . $existing;
        }
        if (isset($seen[$key])) {
            throw new InvalidArgumentException(
                'La misma carta está repetida en una sección. Ajusta su cantidad.',
            );
        }
        return [
            'key' => $key,
            'card' => [
                'card_name' => $name,
                'role' => $source['role'] ?? null,
                'quantity' => $quantity,
                'section' => $section,
                'notes' => $source['notes'] ?? null,
                'ygoprodeck_id' => $cardId ?: null,
            ],
        ];
    }
}
