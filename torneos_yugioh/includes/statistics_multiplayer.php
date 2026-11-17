<?php
declare(strict_types=1);

const YGO_FFA_RATING_VERSION='STATION_X_FFA_ELO_1.0';

/** Team means; both sides are updated simultaneously from their pre-match ratings. */
function ygo_stats_tag_elo(array $left,array $right,float $score,float $k=20): array {
    if(count($left)!==2||count($right)!==2||!in_array($score,[0.0,.5,1.0],true))throw new InvalidArgumentException('Tag Elo requiere dos integrantes por lado y resultado valido.');
    return ygo_stats_elo(array_sum($left)/2,array_sum($right)/2,$score,$k,600);
}

/** Winner-only multinomial Elo. One real table, one winner; sum of deltas is zero. */
function ygo_stats_ffa_elo(array $ratings,int $winner,float $k=20): array {
    if(count($ratings)<3||count($ratings)>4||!array_key_exists($winner,$ratings)||$k<=0)throw new InvalidArgumentException('Mesa Elo multijugador invalida.');
    $max=max($ratings);$weights=[];
    foreach($ratings as $id=>$rating){if(!is_numeric($rating)||!is_finite((float)$rating))throw new InvalidArgumentException('Rating invalido.');$weights[$id]=10**(((float)$rating-$max)/600);}
    $sum=array_sum($weights);$out=[];
    foreach($ratings as $id=>$rating){$expectation=$weights[$id]/$sum;$delta=$k*(($id===$winner?1:0)-$expectation);$out[$id]=['before'=>(float)$rating,'expectation'=>$expectation,'score'=>$id===$winner?1:0,'delta'=>$delta,'after'=>$rating+$delta];}
    return $out;
}

function ygo_stats_group_reason(array $m): ?string {
    if(($m['confirmed']??false)!==true||!in_array($m['result']??'', ['A','B','E'],true))return 'Resultado Tag no confirmado';
    if(empty($m['competitive'])||!empty($m['friendly']))return 'Tag no puntuable';
    if(empty($m['played_at'])||empty($m['format_id'])||empty($m['match_id'])||empty($m['duels']))return 'Tag sin evidencia de juego';
    $teams=[];$decks=[];
    foreach(['participants_a','participants_b'] as $side){if(count($m[$side]??[])!==2)return 'Alineacion Tag incompleta';foreach($m[$side] as $p){
        if(($p['quality']??'')!=='CONFIRMED_AT_ROSTER_FREEZE'||empty($p['team_id'])||empty($p['deck_id'])||($p['format_id']??'')!==$m['format_id'])return 'Identidad historica Tag no confirmada';
        if(isset($teams[$p['team_id']])||isset($decks[$p['deck_id']]))return 'Identidad competitiva repetida en la mesa Tag';$teams[$p['team_id']]=true;$decks[$p['deck_id']]=true;
    }}return null;
}

function ygo_stats_tag_accumulate(array &$r,array $m,array $person,array $opponents,bool $won,bool $draw): void {
    $score=$won?1.0:($draw?.5:0.0);$r['J']++;$r[$won?'V':($draw?'E':'D')]++;
    $r['streak']=$won?$r['streak']+1:0;$r['best_streak']=max($r['best_streak'],$r['streak']);
    foreach($opponents as $o){$r['opponents'][$o['team_id']]=true;if($won)$r['rivals_beaten'][$o['team_id']]=true;}
    if($r['last_at']!==null&&(new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($m['played_at']))->days>90)$r['reactivation']=0;
    $r['reactivation']=min(3,$r['reactivation']+1);$r['last_at']=$m['played_at'];$r['recent'][]=$score;$r['evidence'][]=$m['source_key'];
    $r['tag_matches']=($r['tag_matches']??0)+1;$r['shared_duels']=($r['shared_duels']??0)+count($m['duels']);
    // Shared duel wins are not individual duel wins, LP damage or flawless evidence.
    if($won){$r['events_won_match'][$m['event_id']??0]=true;$r['formats'][$m['format_id']]=true;$r['format_moments'][$m['format_id']]??=['sports_effective_at'=>$m['played_at'],'match_id'=>$m['match_id']];}
}

function ygo_stats_apply_tag(array $m,array $policy,array &$global,array &$profiles,array &$ledger): void {
    $format=$m['format_id'];$left=$m['participants_a'];$right=$m['participants_b'];$all=array_merge($left,$right);$before=[];
    foreach($all as $p){$key=$p['team_id'].':'.$format;$global[$key]??=ygo_stats_metric($p['team_id'],$p['deck_id'],$format);$global[$key]['deck_id']=$p['deck_id'];$before[$p['team_id']]=$global[$key]['rating'];}
    $k=(float)$policy['k_mapping'][$m['ranking_impact']];$score=$m['result']==='A'?1.0:($m['result']==='E'?.5:0.0);
    $change=ygo_stats_tag_elo(array_map(fn($p)=>$before[$p['team_id']],$left),array_map(fn($p)=>$before[$p['team_id']],$right),$score,$k);$changes=[];
    foreach([['A',$left,$right,$change['delta_a'],$change['before_b']],['B',$right,$left,$change['delta_b'],$change['before_a']]] as [$side,$members,$opponents,$delta,$rivalMean])foreach($members as $p){
        $key=$p['team_id'].':'.$format;$profile=$p['team_id'];$profiles[$profile]??=ygo_stats_metric($profile,$p['deck_id'],'*');
        $won=$m['result']===$side;$draw=$m['result']==='E';ygo_stats_tag_accumulate($global[$key],$m,$p,$opponents,$won,$draw);ygo_stats_tag_accumulate($profiles[$profile],$m,$p,$opponents,$won,$draw);
        $global[$key]['rival_pre_sum']+=$rivalMean;$global[$key]['rating']=$before[$profile]+$delta;
        foreach(ygo_stats_achievements($profiles[$profile]) as $a)if($a['status']==='DESBLOQUEADO')$profiles[$profile]['achievement_moments'][$a['code']]??=['sports_effective_at'=>$m['played_at'],'match_id'=>$m['match_id']];
        $changes[]=['team_id'=>$profile,'deck_id'=>$p['deck_id'],'side'=>$side,'before'=>$before[$profile],'delta'=>$delta,'after'=>$before[$profile]+$delta,'rival_mean'=>$rivalMean];
    }
    $ledger[]=array_merge($change,['kind'=>'tag','source_key'=>$m['source_key'],'match_id'=>$m['match_id'],'format_id'=>$format,'season_id'=>$m['season_id'],'event_id'=>$m['event_id'],'played_at'=>$m['played_at'],'result'=>$m['result'],'team_a_id'=>null,'team_b_id'=>null,'deck_a_id'=>null,'deck_b_id'=>null,'participants'=>$changes,'source_version'=>$m['source_version'],'algorithm_version'=>YGO_STATS_VERSION]);
}

function ygo_stats_calculate_ffa(array $matches,array $policy=[],?string $cutoff=null): array {
    $cutoff??=(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
    $policy=array_replace(['base'=>1000,'k'=>20,'divisor'=>600,'minimum_matches'=>10,'minimum_opponents'=>3,'inactive_days'=>90,'version'=>YGO_FFA_RATING_VERSION],$policy);
    usort($matches,static fn($a,$b)=>[$a['played_at']??'',(int)$a['match_id']]<=>[$b['played_at']??'',(int)$b['match_id']]);$rows=[];$ledger=[];$pending=[];$seen=[];
    foreach($matches as $m){
        $id=(int)$m['match_id'];if(($m['played_at']??'')>$cutoff)continue;
        if(isset($seen[$id])){if($seen[$id]!==$m)throw new InvalidArgumentException('Mesa FFA duplicada contradictoria.');continue;}$seen[$id]=$m;
        $players=$m['players']??[];$reason=null;$decks=[];$format=$m['format_id']??null;
        if(empty($m['confirmed'])||empty($m['played_at'])||!$format||count($players)<3||count($players)>4||empty($m['competitive']))$reason='Mesa pendiente, no competitiva o sin evidencia confirmada';
        foreach($players as $p){if(empty($p['deck_id'])||empty($p['team_id'])||($p['quality']??'')!=='CONFIRMED_AT_ROSTER_FREEZE'||($p['format_id']??null)!==$format||isset($decks[$p['deck_id']]))$reason='Identidad historica multijugador incompleta o repetida';$decks[$p['deck_id']]=true;}
        if(!isset($decks[$m['winner']??0]))$reason='Ganador de mesa inexistente';
        if($reason){$pending[]=['match_id'=>$id,'reason'=>$reason];continue;}
        $ratings=[];foreach($players as $p){$key=$format.':'.$p['deck_id'];$rows[$key]??=['team_id'=>$p['team_id'],'deck_id'=>$p['deck_id'],'format_id'=>$format,'rating'=>(float)$policy['base'],'J'=>0,'V'=>0,'D'=>0,'opponents'=>[],'last_at'=>null,'reactivation'=>3];$ratings[$p['deck_id']]=$rows[$key]['rating'];}
        $changes=ygo_stats_ffa_elo($ratings,(int)$m['winner'],(float)$policy['k']);$entries=[];
        foreach($players as $p){$key=$format.':'.$p['deck_id'];$r=&$rows[$key];$change=$changes[$p['deck_id']];$r['rating']=$change['after'];$r['J']++;$r[$p['deck_id']===$m['winner']?'V':'D']++;
            foreach($players as $other)if($other['deck_id']!==$p['deck_id'])$r['opponents'][$other['deck_id']]=true;
            if($r['last_at']!==null&&(new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($m['played_at']))->days>$policy['inactive_days'])$r['reactivation']=0;
            $r['reactivation']=min(3,$r['reactivation']+1);$r['last_at']=$m['played_at'];$entries[]=array_merge($p,$change);unset($r);
        }
        $ledger[]=['match_id'=>$id,'event_id'=>$m['event_id'],'season_id'=>$m['season_id'],'format_id'=>$format,'played_at'=>$m['played_at'],'winner'=>$m['winner'],'k'=>$policy['k'],'changes'=>$entries,'source_version'=>$m['source_version']??null];
    }
    foreach($rows as &$r){$inactive=(new DateTimeImmutable($r['last_at']))->diff(new DateTimeImmutable($cutoff))->days>$policy['inactive_days'];$r['state']=$inactive?'INACTIVO':($r['reactivation']<3?'REACTIVACION PENDIENTE':($r['J']<$policy['minimum_matches']||count($r['opponents'])<$policy['minimum_opponents']?'PROVISIONAL':'CLASIFICADO'));$r['WR']=100*$r['V']/$r['J'];}unset($r);
    usort($rows,static fn($a,$b)=>strcmp($a['format_id'],$b['format_id'])?:($b['rating']<=>$a['rating'])?:($b['V']<=>$a['V'])?:($a['deck_id']<=>$b['deck_id']));$position=[];
    foreach($rows as &$r)$r['position']=$r['state']==='CLASIFICADO'?($position[$r['format_id']]=($position[$r['format_id']]??0)+1):null;unset($r);
    return ['version'=>YGO_FFA_RATING_VERSION,'policy'=>$policy,'ranking'=>array_values($rows),'ledger'=>$ledger,'pending'=>$pending];
}
