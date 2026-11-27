<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Presentation;

final class UiLabelProjection
{
    public static function eventName(string $name): string {
        if(preg_match('/^(.+?)\s+League$/iu',trim($name),$m))return 'Liga '.$m[1];
        return preg_replace('/\binaguracion temporada\b/iu','inauguración de temporada',$name)??$name;
    }
    
    public static function catalog(array $divisions,array $competitions): array {
        $out=['events'=>[],'events_by_id'=>[],'divisions'=>[],'division_short'=>[],'cups'=>[]];
        foreach($competitions as $event){
            $label=self::eventName((string)$event['name']);
            $out['events'][$event['name']]=$label;
            $out['events_by_id'][(string)$event['id']]=['name'=>$label,'type'=>$event['type']];
            if($event['type']==='cup')$out['cups'][]=$label;
        }
        $ordinals=['Primera','Segunda','Tercera','Cuarta','Quinta','Sexta','Séptima','Octava','Novena','Décima'];
        foreach($divisions as $code=>$division){
            $owner=(string)($division['league_competition_id']??'');$event=$out['events_by_id'][$owner]??null;
            $short=(string)($division['name']??$division['short']??$code);
            if($event&&$event['type']==='league'){
                $own=array_values(array_filter($divisions,fn($d)=>(string)($d['league_competition_id']??'')===$owner));
                usort($own,fn($a,$b)=>[(int)($a['tier_order']??0),(int)$a['id']]<=>[(int)($b['tier_order']??0),(int)$b['id']]);
                $index=array_search((int)$division['id'],array_map(fn($d)=>(int)$d['id'],$own),true);
                $short=count($own)===1?'División Única':(isset($ordinals[$index])?$ordinals[$index].' División':'División '.($index+1));
                $full=$event['name'].' — '.$short;
            }else $full=$short;
            $out['divisions'][(string)$code]=$full;$out['division_short'][(string)$code]=$short;
        }
        return $out;
    }
    
    public static function matchLabel(array $match,string $code,mixed $round,array $labels): string {
        $event=$labels['events_by_id'][(string)($match['competition_id']??'')]??null;
        $name=$event&&$event['type']==='league'?($labels['divisions'][$code]??$event['name']):($event['name']??$labels['divisions'][$code]??'Evento');
        return $name.' — Ronda '.$round.' — Mesa '.($match['mesa']??'-');
    }
}
