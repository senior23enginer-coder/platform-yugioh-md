<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Leagues;

final readonly class SyncFormatCategories
{
    public function __construct(private FormatCategoryStore $store) {}

    public function execute(int $seasonId, array $divisionDefinitions): void
    {
        if ($this->store->managed($seasonId)) {
            return;
        }
        $legacyCodes = [
            'TITAN' => 'TCG_AVANZADO',
            'LEGEND' => 'OCG',
            'ELITE' => 'GOAT',
            'CHALLENGER' => 'EDISON',
        ];
        foreach ($legacyCodes as $legacyCode => $newCode) {
            if (!isset($divisionDefinitions[$newCode])) {
                continue;
            }
            $this->store->rename(
                $seasonId,
                $legacyCode,
                $newCode,
                $divisionDefinitions[$newCode],
                array_search($newCode, array_keys($divisionDefinitions), true) + 1,
            );
        }
        $existingCodes = $this->store->codes($seasonId);
        if (!$existingCodes) {
            return;
        }
        foreach ($divisionDefinitions as $code => $definition) {
            $tier = array_search($code, array_keys($divisionDefinitions), true) + 1;
            if (!in_array($code, $existingCodes, true)) {
                continue;
            }
            $this->store->update($seasonId, $code, $definition, $tier);
        }
    }
}
