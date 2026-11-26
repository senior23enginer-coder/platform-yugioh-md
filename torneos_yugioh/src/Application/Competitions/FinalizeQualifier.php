<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use StationX\Yugioh\Domain\Competitions\QualifierFinalizationPolicy;

final readonly class FinalizeQualifier
{
    public function __construct(
        private QualifierFinalizationReader $reader,
        private QualifierFinalizationWriter $writer,
    ) {}

    public function select(int $postedId): ?int
    {
        if ($postedId > 0) {
            $found = $this->reader->postedQualifier($postedId);
            if ($found > 0) {
                return $found;
            }
        }
        $found = $this->reader->defaultQualifier($this->reader->currentSeasonId());
        return $found > 0 ? $found : null;
    }

    public function check(?int $competitionId, int $postedId): array
    {
        $competitionId = $competitionId ?: $this->select($postedId);
        if (!$competitionId) {
            return ['ok' => false, 'reasons' => ['Selecciona un clasificatorio valido.']];
        }
        $required = QualifierFinalizationPolicy::requiredMatches(
            $this->reader->requiredMatches($competitionId),
        );
        $matches = $this->reader->matches($competitionId);
        $reasons = QualifierFinalizationPolicy::roundReasons($matches);
        if (!$matches) {
            return ['ok' => false, 'reasons' => $reasons];
        }
        return QualifierFinalizationPolicy::result(
            $reasons,
            $this->reader->standings($competitionId),
            $required,
        );
    }

    public function execute(?int $competitionId, int $postedId): bool
    {
        $competitionId = $competitionId ?: $this->select($postedId);
        if (!$competitionId) {
            return false;
        }
        $seasonId = $this->reader->currentSeasonId();
        $official = $this->reader->divisions($seasonId);
        if (!$official || !$this->check($competitionId, $postedId)['ok']) {
            return false;
        }
        $standings = $this->reader->standings($competitionId);
        foreach (QualifierFinalizationPolicy::divisions($official, $standings) as $division) {
            foreach ($division['assignments'] as $assignment) {
                $this->writer->assign(
                    $division['divisionId'],
                    $assignment['deckId'],
                    $assignment['rank'],
                );
            }
            $this->writer->updateDivisionCount($division['divisionId'], $division['size']);
        }
        $this->writer->regenerateLeagues($seasonId);
        $this->writer->audit($competitionId, count($standings));
        return true;
    }
}
