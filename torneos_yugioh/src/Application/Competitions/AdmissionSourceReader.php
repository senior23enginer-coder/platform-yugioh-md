<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface AdmissionSourceReader
{
    public function competition(int $id, bool $lock): array;
    public function matches(int $id): array;
    public function normalizedStatus(string $status): string;
    public function workflow(array $competition): ?array;
    public function divisions(int $id): array;
    public function workflowResult(int $matchId): array;
    public function standings(int $id): array;
    public function divisionBelongs(int $division, int $id, int $seasonId): bool;
    public function honors(int $id): array;
    public function finals(int $id): array;
    public function roster(int $id): array;
    public function rulesets(): array;
    public function deckFormats(array $ids): array;
    public function edges(): array;
    public function baseTemplate(string $code): string;
    public function validateFormatRoster(string $format, array $ids): void;
}
