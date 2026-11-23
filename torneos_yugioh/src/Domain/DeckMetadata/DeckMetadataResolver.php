<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\DeckMetadata;

/** Chooses existing descriptions; it never infers a play style from a format or card name. */
final class DeckMetadataResolver
{
    /** Empty editorial placeholders and format names are not descriptions of a play style. */
    public static function description(mixed $value): string
    {
        $value = trim((string) $value);
        return preg_match(
            '/^(?:editable|pendiente(?: de documentar)?|sin (?:datos|estilo)|n\/a|null|tcg|tcg advanced|ocg|goat|rush[ _-]?duel)$/iu',
            $value,
        ) ||
            preg_match(
                '/^(?:consultar el plan|dictamen(?: editorial)?|parcial_avanzado|cat[aá]logo completo|estudio individual)/iu',
                $value,
            ) ||
            preg_match('/\\b(?:CID\\s*\\d+|[CV]\\d{2}|A01-T\\d+)\\b/iu', $value)
            ? ''
            : $value;
    }

    /** Strip editorial metadata without changing the real archetype. */
    public static function archetype(mixed $value): string
    {
        $name = self::description($value);
        return trim(
            (string) preg_replace(
                '/\\s+[—–-]\\s+(?:estudio individual|profundizaci[oó]n|iteraci[oó]n|auditor[ií]a)\\b.*$/iu',
                '',
                $name,
            ),
        );
    }

    /** Only the current, GPT-certified, imported study supplies the player-facing style and archetype. */
    public static function resolve(
        string $name,
        mixed $nativeStyle,
        mixed $nativeArchetype,
        mixed $manualStyle,
        DeckStudyProfile $study,
        int $cardCount,
    ): DeckMetadata {
        $style = self::description($study->strategy) ?: self::description($study->style);
        $archetype = self::archetype($study->archetype);
        return new DeckMetadata($style, $archetype ?: 'Sin análisis importado', $cardCount);
    }
}
