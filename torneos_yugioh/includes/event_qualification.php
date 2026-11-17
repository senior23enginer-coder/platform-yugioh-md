<?php
declare(strict_types=1);

/** Selection operates on verified, closed source standings. It never manufactures substitutes. */
function ygo_qualification_candidates(array $source,array $row): array
{
    $mode=(string)($row['mode']??'');$ranking=$source['ranking']??[];
    if(!in_array($mode,['winner','runner_up','top_n','percentage','minimum_points','best_record','group_position'],true))throw new InvalidArgumentException('Criterio de clasificacion desconocido.');
    if(!empty($source['grouped']) && $mode!=='group_position')throw new InvalidArgumentException('Grupos sin final requiere seleccionar Posicion de grupo; no existe una tabla general.');
    $threshold=filter_var($row['threshold']??0,FILTER_VALIDATE_INT);
    if($threshold===false || $threshold<0 || $threshold>1000000)throw new InvalidArgumentException('El umbral debe ser un entero entre 0 y 1000000.');
    if($mode==='winner' || $mode==='runner_up'){
        $id=(int)($source[$mode==='winner'?'champion_id':'runner_up_id']??0);
        if(!$id || !in_array($id,array_map('intval',array_column($ranking,'id')),true))throw new InvalidArgumentException('La fuente no tiene '.($mode==='winner'?'campeon':'subcampeon').' verificable.');
        return array_values(array_filter($ranking,static fn($r)=>(int)$r['id']===$id));
    }
    if($mode==='minimum_points')$ranking=array_values(array_filter($ranking,static fn($r)=>(int)($r['pts']??$r['points']??0)>=$threshold));
    if($mode==='group_position'){
        if($threshold<1 || empty($source['grouped']))throw new InvalidArgumentException('Selecciona una posicion positiva de una fuente con grupos verificados.');
        $ranking=array_values(array_filter($ranking,static fn($r)=>(int)($r['group_position']??0)===$threshold));
    }
    if($mode==='best_record'){
        foreach($ranking as $i=>&$r){
            $r['_qualification_seed']=$i;
            if((int)($r['pj']??0)<1)throw new InvalidArgumentException('Mejor record requiere encuentros disputados y victorias verificables.');
        }unset($r);
        usort($ranking,static fn($a,$b)=>((int)$b['pg']*(int)$a['pj'])<=>((int)$a['pg']*(int)$b['pj'])
            ?: (int)$a['pp']<=>(int)$b['pp'] ?: $a['_qualification_seed']<=>$b['_qualification_seed']);
        foreach($ranking as &$r)unset($r['_qualification_seed']);unset($r);
    }
    return $ranking;
}

function ygo_qualification_mode(string $rule): ?string
{
    return ['TOP_PERCENTAGE'=>'percentage','MINIMUM_POINTS'=>'minimum_points','BEST_RECORD'=>'best_record','RUNNER_UP'=>'runner_up','GROUP_POSITION'=>'group_position'][$rule]??null;
}
