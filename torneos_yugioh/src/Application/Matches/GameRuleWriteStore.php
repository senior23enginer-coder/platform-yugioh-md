<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Matches;

interface GameRuleWriteStore
{
    public function currentSeasonId(): int;

    public function slug(string $name): string;

    public function saveRuleset(array $values): void;

    public function saveRule(int $ruleId, int $seasonId, string $code, array $values): int;

    public function replaceConditions(int $ruleId, iterable $rows): void;

    public function archive(int $ruleId, int $seasonId): void;

    public function audit(string $entity, ?int $id, string $action, array $payload): void;
}
