<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;

final readonly class ReadAdmissionSource
{
    public function __construct(private AdmissionSourceReader $reader, private ResolveAdmissionDeckFormat $format) {}

    public function execute(int $id, int $division = 0, bool $lock = false): array
    {
        $c=$this->reader->competition($id,$lock);
        if(!$c || !in_array($this->reader->normalizedStatus((string)$c['status']),['completed','closed','archived'],true))throw new InvalidArgumentException('La fuente debe ser un evento finalizado.');
        if((int)($c['participants_per_side']??1)>1 || ($c['match_format']??'1v1')!=='1v1')throw new InvalidArgumentException('La admision de equipos requiere alineaciones y resultados colectivos verificados; esta admision es de decks individuales.');
        $matches=$this->reader->matches($id);
        if(!$matches)throw new InvalidArgumentException('La fuente no tiene combates con resultados verificables.');
        foreach($matches as $m)if($m['status']!=='FINALIZADA' || !in_array($m['result'],['A','B','E'],true))throw new InvalidArgumentException('La fuente tiene resultados pendientes o invalidos.');
        $wf=$this->reader->workflow($c);if($wf!==null && empty($wf['complete']))throw new InvalidArgumentException('La fuente aun tiene fases pendientes.');
        $grouped=($wf['policy']['model']??'')==='groups';
        if(!$division && $c['type']==='league' && count($this->reader->divisions($id))>1)throw new InvalidArgumentException('Selecciona una division concreta de la liga de origen.');
        if($wf!==null)foreach($wf['history'] as $m){$r=$this->reader->workflowResult((int)$m['match_id']);if($r['winner']!==$m['winner'] || $r['game_difference']!==$m['game_difference'])throw new InvalidArgumentException('Un resultado de la fuente difiere del cierre registrado.');}
        $ranking=$this->reader->standings($id);
        if($division){
            if(!$this->reader->divisionBelongs($division,$id,(int)$c['season_id']))throw new InvalidArgumentException('La division no pertenece a la liga de origen.');
            $eligible=[];foreach($matches as $m)if((int)$m['division_id']===$division){$eligible[]=(int)$m['deck_a_id'];$eligible[]=(int)$m['deck_b_id'];}
            $ranking=array_values(array_filter($ranking,static fn($r)=>in_array((int)$r['id'],$eligible,true)));
        }
        $honors=$this->reader->honors($id);
        $champion=0;$runner=0;
        if($division || ($c['type']==='league' && count($this->reader->divisions($id))<=1))$champion=(int)($ranking[0]['id']??0);
        elseif(count($honors)===1)$champion=(int)$honors[0]['champion_deck_id'];
        if($wf!==null && !empty($wf['complete'])){$champion=(int)($wf['champion']??0);$runner=(int)($wf['runner_up']??0);}
        if($division || ($c['type']==='league' && count($this->reader->divisions($id))<=1))$runner=(int)($ranking[1]['id']??0);
        elseif(count($honors)===1 && !$runner)$runner=(int)$honors[0]['runner_up_deck_id'];
        if($wf===null && $champion && in_array($c['execution_model'],['single_elimination','bracket','top_cut','playoffs'],true)){
            $finals=$this->reader->finals($id);
            if(count($finals)!==1 || !in_array($finals[0]['result'],['A','B'],true) || (int)$finals[0][$finals[0]['result']==='A'?'deck_a_id':'deck_b_id']!==$champion)throw new InvalidArgumentException('El titulo de la fuente no coincide con una final resuelta.');
        }
        if($runner && ($runner===$champion || !in_array($runner,array_map('intval',array_column($ranking,'id')),true)))throw new InvalidArgumentException('El subcampeon no coincide con la clasificacion de la fuente.');
        if(!empty($finals) && count($finals)===1 && $runner && (int)$finals[0][$finals[0]['result']==='A'?'deck_b_id':'deck_a_id']!==$runner)throw new InvalidArgumentException('El subcampeon no coincide con la final resuelta.');
        if($champion && !in_array($champion,array_column($ranking,'id')))throw new InvalidArgumentException('El campeon no coincide con los participantes clasificados.');
        if($champion && count($honors)===1){$runner=(int)$honors[0]['runner_up_deck_id'];$order=array_flip(array_column($ranking,'id'));$order[$champion]=-2;if($runner)$order[$runner]=-1;usort($ranking,static fn($a,$b)=>$order[$a['id']]<=>$order[$b['id']]);}
        if(in_array($c['execution_model'],['single_elimination','bracket','top_cut','playoffs','double_elimination','groups_knockout','swiss_top_cut','season_playoffs'],true)){
            $finish=[];$tie=array_flip(array_column($ranking,'id'));
            foreach($matches as $m)if(in_array($m['phase'],['cup','final','world','playoff','top_cut','double_elimination'],true) && in_array($m['result'],['A','B'],true))$finish[(int)$m[$m['result']==='A'?'deck_b_id':'deck_a_id']]=(int)$m['round_number'];
            if($champion)$finish[$champion]=PHP_INT_MAX;
            usort($ranking,static fn($a,$b)=>($finish[$b['id']]??0)<=>($finish[$a['id']]??0) ?: $tie[$a['id']]<=>$tie[$b['id']]);
        }
        if(!$ranking)throw new InvalidArgumentException('La fuente no tiene una clasificacion verificable.');
        $deckFormat=$this->format->execute(array_map('intval',array_column($ranking,'id')));
        if($c['event_ruleset']!=='custom' && $deckFormat!==$c['event_ruleset'])throw new InvalidArgumentException('El formato de los decks no coincide con el declarado por la fuente.');
        $roster=$this->reader->roster($id);
        $rankSignature=array_map(static fn($r)=>array_intersect_key($r,array_flip(['id','pts','pj','pg','pe','pp','cg','cp','dp','seed_rank','buchholz','group','group_position'])),$ranking);
        return ['id'=>$id,'division_id'=>$division,'name'=>$c['name'],'season_id'=>(int)$c['season_id'],'format'=>$c['event_ruleset'],'deck_format'=>$deckFormat,'champion_id'=>$champion,'runner_up_id'=>$runner,'grouped'=>$grouped,'ranking'=>$ranking,'signature'=>hash('sha256',json_encode([$c['event_ruleset'],$deckFormat,$matches,$honors,$roster,$rankSignature],JSON_UNESCAPED_UNICODE))];
    }
}
