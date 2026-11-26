<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

interface BootstrapDataPort
{
    public function statistics(): array;
    public function executionCapabilities(): array;
    public function executionModels(array $types): array;
    public function freeForAllMatches(): array;
    public function canEdit(): bool;
    public function teamResults(): array;
    public function teamConfiguration(array $competition): mixed;
    public function workflow(array $competition): mixed;
    public function recoveryToken(mixed $workflow): mixed;
    public function admissionSources(): array;
    public function admission(array $competition): mixed;
    public function eventCatalog(): array;
    public function eventDescriptors(): mixed;
    public function eventTemplates(): array;
}
