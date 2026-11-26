<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Statistics;

final class StatisticsRevisionPolicy
{
    public static function canonical(mixed $value): string
    {
        $normalize=static function(mixed $v) use (&$normalize): mixed {if(!is_array($v))return $v;if(!array_is_list($v))ksort($v);foreach($v as &$item)$item=$normalize($item);unset($item);return $v;};
        return json_encode($normalize($value),JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
    }

    public static function publicValues(array $data): array
    {
        foreach($data as &$value)if(is_array($value))$value=self::publicValues($value);unset($value);
        if(($data['name']??null)==='Logro secreto' && isset($data['code']))$data['code']='secret_'.substr(hash('sha256',(string)$data['code']),0,12);
        return $data;
    }

    public static function policy(?string $custom): array
    {
        $policy = StatisticsPolicy::defaults();
        if ($custom) {
            $policy = array_replace($policy, json_decode($custom, true));
        }
        $policy['global_cadence'] = 'per_result';
        $policy['minimum_matches'] = 2;
        $policy['minimum_opponents'] = 1;
        $policy['version'] = StatisticsPolicy::VERSION;
        return $policy;
    }

    public static function fixedBonuses(array $source, array $policy): array
    {
        foreach($source['events'] as &$e){if($e['type']==='friendly')continue;$e['fixed_bonus']??=$policy['medal_bonus'][$e['type']]??20;$e['bonus_version']??=$policy['version'];}unset($e);
        return $source;
    }

    public static function publishedTiers(array $cuts): array
    {
        $tiers=[];foreach($cuts as $cut)if($cut['kind']==='metagame')foreach($cut['data']['metagame']??[] as $t)$tiers[]=array_merge($t,['published_at'=>$cut['published_at'],'status'=>'PUBLICADO']);
        return $tiers;
    }

    public static function sourceHash(array $source, array $policy, array $cuts, string $date): string
    {
        return hash('sha256', self::canonical([$source, $policy, array_column($cuts, 'id'), $date]));
    }

    public static function preserveRevocations(array $data, ?string $previousPayload): array
    {
        if($previousPayload){$old=json_decode($previousPayload,true);$oldByKey=[];foreach($old['global']??[] as $row)$oldByKey[$row['team_id'].':'.$row['format_id']]=$row;
            foreach($data['global'] as &$row){$prior=$oldByKey[$row['team_id'].':'.$row['format_id']]??null;if(!$prior)continue;$oldAchievements=array_column($prior['achievements']??[],null,'code');
                foreach($row['achievements'] as &$achievement){$oldState=$oldAchievements[$achievement['code']]['status']??null;
                    if(in_array($oldState,['DESBLOQUEADO','REVOCADO'],true)&&$achievement['status']!=='DESBLOQUEADO'){$achievement['status']='REVOCADO';$achievement['review_reason']='La evidencia confirmada actual ya no cumple la condicion.';}
                }unset($achievement);
            }unset($row);
            $currentKeys=[];foreach($data['global'] as $row)$currentKeys[$row['team_id'].':'.$row['format_id']]=true;
            foreach($oldByKey as $key=>$prior)if(!isset($currentKeys[$key])){
                $empty=MetricFactory::metric((int)$prior['team_id'],(int)$prior['deck_id'],(string)$prior['format_id']);
                $empty=array_merge($empty,['bonus_medallas'=>0,'total'=>1000.0,'state'=>'PROVISIONAL','sample_state'=>'PROVISIONAL','championships'=>0,'WR'=>null,'performance'=>null,'position'=>null]);
                $empty['achievements']=AchievementPolicy::achievements($empty);$oldAchievements=array_column($prior['achievements']??[],null,'code');
                foreach($empty['achievements'] as &$a)if(in_array($oldAchievements[$a['code']]['status']??'', ['DESBLOQUEADO','REVOCADO'],true)){$a['status']='REVOCADO';$a['review_reason']='La fuente confirmada ya no contiene la evidencia.';}unset($a);
                $data['global'][]=$empty;
            }
        }
        return $data;
    }

    public static function publication(array $cut, bool $full): array
    {
        $d=json_decode($cut['payload'],true);unset($cut['payload']);
        if(!$full){$small=['version'=>$d['version'],'cutoff'=>$d['cutoff'],'status'=>$d['status'],'summary_only'=>true,'global'=>[],'metagame'=>[],'free_for_all'=>['ranking'=>$d['free_for_all']['ranking']??[]]];
            foreach($d['global']??[] as $row)$small['global'][]=array_intersect_key($row,array_flip(['team_id','deck_id','format_id','position','rating','bonus_medallas','total','state','J','opponents','championships']));
            foreach($d['metagame']??[] as $row)$small['metagame'][]=array_intersect_key($row,array_flip(['season_id','format_id','deck_id','team_id','tier','IC','R','EL','C','RE','RD','rating_season','match_WR','J','JD','quality_status']));
            $d=$small;
        }
        $cut['data']=$d;return $cut;

    }
}
