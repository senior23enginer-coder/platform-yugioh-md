<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

interface CompetitionDefinitionServices
{
    public function currentSeasonId(): int;

    public function assertMutable(int $id): void;

    public function generateCode(int $seasonId, string $type, string $ruleset, string $name, int $id): string;

    public function templateType(string $template): string;

    public function requireExecutionType(string $type, string $execution): void;

    public function choiceErrors(array $input): array;

    public function templates(): array;

    public function uploadLogo(): string;

    public function validGalleryLogo(string $url, string $type): string;

    public function gallery(): array;

    public function validGameRule(int $seasonId, int $posted, ?int $fallback, string $type, string $execution): ?int;

    public function parameterFields(string $execution): array;

    public function contractErrors(array $input, ?array $preset): array;

    public function history(int $seasonId, int $id, ?string $from, string $to, string $action, array $payload): void;

    public function recordLogo(string $entity, int $id, string $url, string $source): void;

    public function persistCalendar(int $seasonId, int $id): void;
}
