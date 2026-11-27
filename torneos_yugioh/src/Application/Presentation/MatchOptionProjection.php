<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class MatchOptionProjection
{
    public static function build(
        array $fixtures,
        array $presentationLabels,
        callable $deckMatchLabel,
        callable $matchLabel,
    ): array {
        $matchOptions = [];
        foreach ($fixtures as $code => $rounds) {
            foreach ($rounds as $round) {
                foreach ($round['matches'] as $match) {
                    $matchOptions[] = [
                        'id' => (int) ($match['id'] ?? 0),
                        'label' =>
                            $matchLabel(
                                $match,
                                (string) $code,
                                $round['ronda'],
                                $presentationLabels,
                            ) .
                            ': ' .
                            $deckMatchLabel((string) $match['a']) .
                            ' vs ' .
                            $deckMatchLabel((string) $match['b']),
                    ];
                }
            }
        }
        return $matchOptions;
    }
}
