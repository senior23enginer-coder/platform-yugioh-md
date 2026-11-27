<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;

final readonly class ListAdmissionSources
{
    public function __construct(
        private AdmissionDirectoryReader $reader,
        private ReadAdmissionSource $source,
    ) {}

    public function execute(): array
    {
        $out = [];
        foreach ($this->reader->completedCompetitions() as $c) {
            $divisions = $c['type'] === 'league' ? $this->reader->divisions((int) $c['id']) : [];
            $choices = count($divisions) > 1 ? $divisions : [['id' => 0, 'name' => '']];
            foreach ($choices as $d) {
                try {
                    $r = $this->source->execute((int) $c['id'], (int) $d['id']);
                    $r['label'] = $d['id'] ? $d['name'] : $c['name'];
                    $r['season_name'] = $c['season_name'];
                    unset($r['signature']);
                    $out[] = $r;
                } catch (InvalidArgumentException $e) {
                    /* An incomplete source is not offered as an eligible source. */
                }
            }
        }
        return $out;
    }
}
