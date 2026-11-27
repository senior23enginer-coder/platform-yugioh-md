<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Competitions\QualificationPolicy;
use StationX\Yugioh\Domain\Competitions\AdmissionQuotaPolicy;

final readonly class ResolveEventAdmission
{
    public function __construct(private AdmissionSourceReader $reader, private ReadAdmissionSource $source) {}

    public function execute(array $c, array $a, bool $lock = false): array
    {
        if(($a['version']??'')!=='STATION_X_ADMISSION_V1')throw new InvalidArgumentException('Version de admision desconocida.');
        $rows=$a['sources']??[];
        $expectedMode=QualificationPolicy::mode((string)($c['advancement_rule']??''));
        if($expectedMode!==null)foreach($rows as $row)if(($row['mode']??'')!==$expectedMode)throw new InvalidArgumentException('Las fuentes deben usar el criterio de avance guardado en la ficha.');
        $quotas=AdmissionQuotaPolicy::quotas($rows,(int)($a['capacity']??0));$ids=[];$origins=[];$signatures=[];$seen=[];
        $actualFormat=null;
        foreach($rows as $i=>$row){
            $sourceId=(int)($row['source_id']??0);$division=(int)($row['division_id']??0);$key=$sourceId.':'.$division;
            if(filter_var($row['source_id']??0,FILTER_VALIDATE_INT)===false || $sourceId<1 || filter_var($row['division_id']??0,FILTER_VALIDATE_INT)===false || $division<0)throw new InvalidArgumentException('Identificador de fuente o division invalido.');
            if($sourceId===(int)$c['id'] || isset($seen[$key]))throw new InvalidArgumentException('No se puede repetir una fuente ni usar el propio evento.');$seen[$key]=true;
            $source=$this->source->execute($sourceId,$division,$lock);
            if($source['format']!==$c['event_ruleset'])throw new InvalidArgumentException('Todas las fuentes deben tener el mismo formato que el evento de destino.');
            if($actualFormat!==null && $actualFormat!==$source['deck_format'])throw new InvalidArgumentException('Los decks de las fuentes son de formatos diferentes.');$actualFormat=$source['deck_format'];
            if(in_array($this->reader->baseTemplate($c['prompt_template']??''),['super_cup','grand_final'],true) && $row['mode']!=='winner')throw new InvalidArgumentException('Esta plantilla admite campeones verificados: usa Campeon en cada fuente.');
            $candidates=QualificationPolicy::candidates($source,$row);
            if($row['mode']==='group_position' && count($candidates)!==$quotas[$i])throw new InvalidArgumentException('Los cupos de Posicion de grupo deben incluir esa posicion de todos los grupos, sin recortes arbitrarios.');
            $selected=array_map('intval',array_column(array_slice($candidates,0,$quotas[$i]),'id'));
            if(count($selected)!==$quotas[$i])throw new InvalidArgumentException('La fuente '.$source['name'].' no tiene suficientes participantes para sus cupos.');
            foreach($selected as $id){if(in_array($id,$ids,true))throw new InvalidArgumentException('Un deck clasifica por mas de una fuente. No se asignaran sustitutos automaticamente.');$ids[]=$id;$origins[]=['deck_id'=>$id,'source_id'=>$sourceId,'division_id'=>$division,'mode'=>$row['mode']];}
            $signatures[$key]=$source['signature'];
        }
        if(($c['execution_model']??'')==='series' && count($ids)!==2)throw new InvalidArgumentException('La serie necesita exactamente dos clasificados.');
        $this->reader->validateFormatRoster((string)$c['event_ruleset'],$ids);
        return ['ids'=>$ids,'origins'=>$origins,'quotas'=>$quotas,'signatures'=>$signatures];
    }
}
