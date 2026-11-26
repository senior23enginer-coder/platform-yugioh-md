<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Matches;

final class ManualMatchInput
{
    public static function initial(array $post): array
    {
        $phase = preg_replace(
            '/[^a-z_]/',
            '',
            strtolower((string) ($post['manual_phase'] ?? 'league')),
        );
        if ($phase === '') {
            $phase = 'league';
        }
        $round = max(1, (int) ($post['manual_round'] ?? 1));
        $table = max(1, (int) ($post['manual_table'] ?? 1));
        $deckA = (int) ($post['manual_deck_a_id'] ?? 0);
        $deckB = (int) ($post['manual_deck_b_id'] ?? 0);
        return ['phase' => $phase, 'round' => $round, 'table' => $table, 'deckA' => $deckA, 'deckB' => $deckB];
    }

    public static function details(array $post): array
    {
        $format = preg_replace(
            '/[^a-z0-9_]/',
            '',
            strtolower((string) ($post['manual_match_format'] ?? '1v1')),
        );
        $bestOf = max(1, min(10, (int) ($post['manual_best_of'] ?? 5)));
        $participantsPerSide = max(
            1,
            min(3, (int) ($post['manual_participants_per_side'] ?? 1)),
        );
        if (preg_match('/^([123])v\1$/', $format, $matchParts)) {
            $participantsPerSide = (int) $matchParts[1];
        }
        $deckC =
            ($post['manual_deck_c_id'] ?? '') === '' ? null : (int) $post['manual_deck_c_id'];
        $deckD =
            ($post['manual_deck_d_id'] ?? '') === '' ? null : (int) $post['manual_deck_d_id'];
        $deckE =
            ($post['manual_deck_e_id'] ?? '') === '' ? null : (int) $post['manual_deck_e_id'];
        $deckF =
            ($post['manual_deck_f_id'] ?? '') === '' ? null : (int) $post['manual_deck_f_id'];
        $manualRuleset = preg_replace(
            '/[^a-z0-9_]/',
            '',
            strtolower((string) ($post['manual_ruleset_code'] ?? '')),
        );
        return ['format' => $format, 'bestOf' => $bestOf, 'participants' => $participantsPerSide, 'deckC' => $deckC, 'deckD' => $deckD, 'deckE' => $deckE, 'deckF' => $deckF, 'ruleset' => $manualRuleset];
    }
}
