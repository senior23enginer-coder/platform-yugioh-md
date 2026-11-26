<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface MatchResultRules
{
    public function teamKindForMatch(int $matchId): string;

    public function verifiedStart(int $matchId, int $duel, string $starts): bool;

    public function validateDate(int $matchId): array;

    public function assertEditable(int $matchId): void;

    public function evaluate(int $matchId, array $values): array;
}
