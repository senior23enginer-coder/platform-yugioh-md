<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

final class StatisticsSourceProjection
{
    public static function build(array $events, array $fixedRows, array $rows, array $snapshots, array $collectiveRows, array $honors, array $seasons, array $ffaRows): array
    {
        $fixed=[];foreach($fixedRows as $f)$fixed[$f['event_id']]=$f;
        foreach($events as &$e){$e['format_id']=$e['event_ruleset']?:null;if(isset($fixed[$e['id']])){$e['fixed_bonus']=(int)$fixed[$e['id']]['bonus_points'];$e['bonus_version']=$fixed[$e['id']]['bonus_version'];}}unset($e);
        $identities=[];foreach($snapshots as $s)if(in_array($s['data_origin'],['captured','live','verified','retroactive_verified'],true)&&in_array($s['snapshot_status'],['exact','confirmed','verified','retroactive_verified'],true))$identities[$s['match_id']][$s['side']]=$s;
        $collective=[];foreach($collectiveRows as $record)$collective[$record['season_id'].':'.substr($record['setting_key'],18)]=json_decode($record['setting_value'],true);
        $matches=[];
        foreach($rows as $r){
            $state=strtolower(str_replace(' ','_',(string)$r['status']));$finished=in_array($state,['finalizada','finalizado','completed','finished'],true);
            $details=json_decode($r['duel_details']??'[]',true)?:[];$duels=[];$complete=true;
            foreach(range(1,5) as $i)if(in_array($r['d'.$i]??null,['A','B','E'],true)){
                $detail=array_values(array_filter($details,fn($d)=>is_array($d)&&(int)($d['duel']??0)===$i))[0]??[];
                $duels[]=array_merge($detail,['position'=>$i,'winner'=>$r['d'.$i]]);
            }
            if(!$duels||array_column($duels,'position')!==range(1,count($duels)))$complete=false;
            $winsA=count(array_filter($duels,fn($d)=>$d['winner']==='A'));$winsB=count(array_filter($duels,fn($d)=>$d['winner']==='B'));
            if(!preg_match('/^(\d+)-(\d+)$/',(string)$r['score'],$score)||$winsA!==(int)$score[1]||$winsB!==(int)$score[2])$complete=false;
            $a=$identities[$r['id']]['A']??null;$b=$identities[$r['id']]['B']??null;
            if($a && (int)$a['deck_id']!==(int)$r['deck_a_id'])$a=null;
            if($b && (int)$b['deck_id']!==(int)$r['deck_b_id'])$b=null;
            // Collective sides need a confirmed single competitive identity supplied by their owner.
            // A captain's deck is never silently credited with all teammates' results.
            if((int)($r['participants_per_side']??1)>1){$a=null;$b=null;}
            $originFormat=$a&&$b&&!empty($a['ruleset_code'])&&$a['ruleset_code']===$b['ruleset_code']?$a['ruleset_code']:null;
            $base=['match_id'=>(int)$r['id'],'event_id'=>(int)($r['competition_id']??0),'season_id'=>(int)($r['source_season']??0),
                'format_id'=>$originFormat,'ranking_impact'=>$r['ranking_impact'],'division_id'=>$r['division_id'],'phase'=>$r['phase'],'event_type'=>$r['event_type'],'judge'=>$r['judge'],'venue'=>$r['venue'],
                'confirmed'=>$finished&&in_array($r['result'],['A','B','E'],true),'result'=>$r['result'],
                'competitive'=>$r['ranking_impact']!==null&&$r['ranking_impact']!=='NONE'&&$r['event_type']!=='friendly',
                'friendly'=>$r['event_type']==='friendly','played_at'=>$r['completed_at']??(!empty($r['scheduled_on'])?$r['scheduled_on'].' '.($r['starts_at']?:'00:00:00'):null),
                'played_at_source'=>!empty($r['completed_at'])?'completed_at':'scheduled_legacy',
                'deck_a_id'=>(int)$r['deck_a_id'],'deck_b_id'=>(int)$r['deck_b_id'],
                'team_a_id'=>$a?(int)$a['team_id']:0,'team_b_id'=>$b?(int)$b['team_id']:0,
                'identity_quality'=>$a&&$b?'CONFIRMADA':((int)($r['participants_per_side']??1)>1?'PENDIENTE: resultado colectivo sin identidad equipo/deck unica confirmada':'PENDIENTE: falta snapshot historico confirmado'),
                'duels'=>$duels,'duels_complete'=>$complete,'played'=>count($duels)>0,'dice_winner'=>$r['dice_winner'],
                'source_version'=>(string)$r['result_updated_at'].':'.hash('sha256',json_encode([$r['result'],$duels]))];
            if((int)($r['participants_per_side']??1)>1)array_push($matches,...MultiplayerSourceProjection::team($r,$base,$collective[$r['source_season'].':'.$r['id']]??null));else $matches[]=$base;
        }
        foreach($honors as &$h){$h['confirmed']=in_array(strtolower($h['event_status']??''),['completed','finalizado','finalizada'],true);$h['effective_at']=$h['created_at'];}unset($h);
        foreach($events as &$e){$s=array_values(array_filter($seasons,fn($s)=>(int)$s['id']===(int)$e['season_id']))[0]??[];$e['season_status']=$s['status']??null;}unset($e);
        return ['matches'=>$matches,'events'=>$events,'honors'=>$honors,'seasons'=>$seasons,'ffa_matches'=>MultiplayerSourceProjection::freeForAll($ffaRows)];
    }
}
