<?php
declare(strict_types=1);

/** Optional manual-study fields; no interpretation of card effects. */
function ygo_combo_context(int $deckId): array
{
    $root=dirname(__DIR__,2).'/assets/editorial';$result=['format'=>'unspecified','skills'=>[]];
    $manifest=$root.'/deck-deliveries.json';if(!is_file($manifest))return $result;
    $data=json_decode(file_get_contents($manifest),true);$url=$data['decks'][(string)$deckId]['url']??'';
    $relative=parse_url($url,PHP_URL_PATH);if(!is_string($relative)||!str_starts_with($relative,'./assets/editorial/'))return $result;
    $file=realpath(dirname(__DIR__,2).'/'.substr($relative,2));$allowed=realpath($root);
    if(!$file||!$allowed||!str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$allowed).'/')||!str_ends_with($file,'.json'))return $result;
    $record=json_decode(file_get_contents($file),true);if((int)($record['deckId']??0)!==$deckId)return $result;
    $result['format']=match($record['format']??''){'rush_duel'=>'rush','goat'=>'goat','tcg_advanced','tcg_traditional'=>'tcg',default=>'unspecified'};
    if($result['format']==='rush')foreach($record['skills']??[] as $skill){
        if(empty($skill['skill_id']))continue;
        $result['skills'][]=['id'=>(string)$skill['skill_id'],'name'=>(string)($skill['nombre']??$skill['skill_id']),'requirements'=>(string)($skill['requisito_texto']??''),'effect'=>(string)($skill['efecto_texto']??''),'image'=>(string)($skill['image_url']??''),'source'=>(string)($skill['origen_verificado']??''),'limits'=>$skill['limitations_unverified']??[]];
    }
    return $result;
}

function ygo_combo_details(array $node,array $cards,string $format): array
{
    $result=[];
    if(isset($node['details'])){
        if(!is_array($node['details']))throw new InvalidArgumentException('Detalles técnicos no válidos.');
        $result['details']=[];
        foreach(['cost','target','origin','destination','timing','restrictions','remaining','source'] as $field)$result['details'][$field]=ygo_combo_text($node['details'][$field]??'',1500);
    }
    if(isset($node['placements'])){
        if(!is_array($node['placements'])||!array_is_list($node['placements'])||count($node['placements'])>8)throw new InvalidArgumentException('Estado de zonas no válido.');
        $seen=[];$occupied=[];$result['placements']=[];
        foreach($node['placements'] as $p){
            if(!is_array($p)||!is_int($p['card_index']??null)||!array_key_exists($p['card_index'],$cards)||isset($seen[$p['card_index']]))throw new InvalidArgumentException('La posición no corresponde a una carta del paso.');
            $zone=$p['zone']??'unknown';$position=$p['position']??'unknown';
            if(!is_string($zone)||!preg_match('/^(unknown|hand|gy|banished|deck|extra|side|reserve|field|monster_[1-5]|spell_[1-5]|extra_[12])$/D',$zone)||!in_array($position,['unknown','attack','defense','face_down'],true))throw new InvalidArgumentException('Zona o posición no válida.');
            if(($format==='rush'&&preg_match('/^(?:monster_[45]|spell_[45]|extra_[12])$/D',$zone))||($format==='goat'&&str_starts_with($zone,'extra_')))throw new InvalidArgumentException('Esa zona no pertenece al formato seleccionado.');
            if($format==='unspecified'&&preg_match('/^(?:monster_|spell_|extra_)/',$zone))throw new InvalidArgumentException('Selecciona el formato del tablero antes de indicar zonas numeradas.');
            if(preg_match('/^(?:monster_|spell_|extra_|field)/',$zone)&&isset($occupied[$zone]))throw new InvalidArgumentException('La zona ya contiene una carta. Describe materiales por separado.');
            $seen[$p['card_index']]=true;$occupied[$zone]=true;
            $result['placements'][]=['card_index'=>$p['card_index'],'zone'=>$zone,'position'=>$position,'materials'=>ygo_combo_text($p['materials']??'',500)];
        }
    }
    return $result;
}
