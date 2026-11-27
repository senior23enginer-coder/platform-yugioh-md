<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Persistence\Competitions;

use PDO;
use StationX\Yugioh\Application\Competitions\AdmissionConfigurationStore;
use StationX\Yugioh\Domain\Competitions\AdmissionConfigurationPolicy;

final readonly class PdoAdmissionConfigurationStore implements AdmissionConfigurationStore
{
    public function __construct(private PDO $pdo) {}

    public function load(array $c, bool $lock = false): ?array
    {
        if(empty($c['id']) || empty($c['season_id']))return null;
        $q=$this->pdo->prepare('SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key=?'.($lock?' FOR UPDATE':''));
        $q->execute([$c['season_id'],'event_admission_'.$c['id']]);$v=$q->fetchColumn();
        return $v===false?null:json_decode((string)$v,true,512,JSON_THROW_ON_ERROR);
    }

    public function store(array $c, array $a): void
    {
        $json = AdmissionConfigurationPolicy::encode($a);
        $this->pdo->prepare('INSERT INTO sx_ygo_settings(season_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$c['season_id'],'event_admission_'.$c['id'],$json]);
    }
}
