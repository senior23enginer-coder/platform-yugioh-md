<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;
use StationX\Yugioh\Application\Shared\TransactionBoundary;

final readonly class SaveEventAdmission
{
    public function __construct(
        private AdmissionWriteStore $store,
        private TransactionBoundary $transaction,
    ) {}

    public function execute(int $target, array $rows, int $capacity): array
    {
        return $this->transaction->run(function () use ($target, $rows, $capacity): array {
            $c = $this->store->lockedCompetition($target);
            if (!$c) {
                throw new InvalidArgumentException('Evento inexistente.');
            }
            $this->store->requireOpen((int) $c['season_id']);
            $this->store->assertMutable($target);
            if (
                !empty($c['configuration_locked']) ||
                in_array(
                    $this->store->normalizedStatus((string) $c['status']),
                    ['completed', 'closed', 'cancelled', 'archived'],
                    true,
                )
            ) {
                throw new InvalidArgumentException(
                    'La admision ya esta bloqueada por el estado del evento.',
                );
            }
            $a = [
                'version' => 'STATION_X_ADMISSION_V1',
                'capacity' => $capacity,
                'sources' => array_values($rows),
                'frozen' => false,
            ];
            $this->store->assertNoCycle($target, $a['sources']);
            $resolved = $this->store->resolve($c, $a);
            $this->store->validateRoster($target, $resolved['ids']);
            $this->store->replaceDecks($target, $resolved['ids']);
            $this->store->clearDependencies($target);
            $dependencies = [];
            foreach ($rows as $i => $r) {
                $sid = (int) $r['source_id'];
                $dependencies[$sid] = ($dependencies[$sid] ?? 0) + $resolved['quotas'][$i];
            }
            $this->store->writeDependencies($c, $target, $dependencies);
            $this->store->setParticipantSource($target);
            $a['selection'] = $resolved;
            $this->store->store($c, $a);
            $this->store->refreshGate($target);
            $this->store->audit($target, $a);
            return $a;
        });
    }
}
