<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/competitive_intelligence.php';
$n=0;$results=[];
function ck(string $id,bool $ok,string $expected,mixed $actual):void{global $n,$results;$n++;$results[]=['test_id'=>$id,'status'=>$ok?'PASS':'FAIL','expected'=>$expected,'actual'=>$actual];if(!$ok)throw new RuntimeException($id.' '.$expected.' '.json_encode($actual));}
function sm(int $id=1,string $result='A',array $extra=[]):array{return array_replace(['match_id'=>$id,'event_id'=>10,'season_id'=>1,'format_id'=>'tcg','confirmed'=>true,'competitive'=>true,'result'=>$result,'ranking_impact'=>'FULL','played_at'=>sprintf('2026-10-%02d 19:00:00',min(28,$id)),'team_a_id'=>1,'team_b_id'=>2,'deck_a_id'=>101,'deck_b_id'=>102,'duels_complete'=>true,'duels'=>[['position'=>1,'winner'=>'A'],['position'=>2,'winner'=>'A']]],$extra);}
function calc(array $ms,array $events=[],array $honors=[]):array{return ygo_stats_calculate($ms,$events,$honors,[], '2026-10-30 23:59:59');}
function own(array $c,int $team=1,string $format='tcg'):array{return array_values(array_filter($c['global'],fn($r)=>$r['team_id']===$team&&$r['format_id']===$format))[0];}
$c=calc([sm(1,'A',['duels'=>array_map(fn($i)=>['position'=>$i,'winner'=>$i<=3?'A':'B'],range(1,5))])]);$r=own($c);
ck('QA-01',$r['V']===1&&$r['VG']===3,'1 match win and 3 duel wins',[$r['V'],$r['VG']]);
$r=own(calc([sm(1),sm(2,'E')]));ck('QA-02',$r['WR']===50.0&&$r['performance']===75.0,'WR 50 performance 75',[$r['WR'],$r['performance']]);
ck('QA-03',ygo_stats_ratio(0,0)===null,'NULL for zero denominator',ygo_stats_ratio(0,0));
$r=own(calc([sm()]));ck('QA-05',$r['flawless']===0,'Missing LP cannot unlock flawless',$r['flawless']);
$d=['lp_start_a'=>8000,'lp_a'=>8000,'lp_history_complete'=>true,'lp_history'=>[['a'=>7000],['a'=>8000]]];ck('QA-06',!ygo_stats_flawless($d,'A'),'LP lost then restored is not flawless',ygo_stats_flawless($d,'A'));
foreach([['E','B','A'],['B','B','A']] as $i=>$vs){$m=sm(1,'A',['duels'=>array_map(fn($j)=>['position'=>$j+1,'winner'=>$vs[$j]],array_keys($vs))]);$r=own(calc([$m]));ck($i?'QA-08':'QA-07',$r['reverse_sweeps']===($i?1:0),'Only explicit D1 and D2 losses qualify',$r['reverse_sweeps']);}
$r=own(calc([sm()]));ck('QA-09',$r['second_w']===0&&$r['first_n']===0,'Unknown starter not second',$r['second_w']);
$r=own(calc([sm(1,'B',['duels'=>[['position'=>5,'winner'=>'A']]])]));ck('QA-10',$r['d5_wins']===0,'D5 without match win not achievement',$r['d5_wins']);
$ach=array_column(own(calc([sm()]))['achievements'],null,'code');ck('QA-11',$ach['season_no_surrender']['status']!=='DESBLOQUEADO','Open season not achievement',$ach['season_no_surrender']['status']);
$ms=[];foreach(range(1,5) as $i)$ms[]=sm($i,'A',['team_b_id'=>2+$i,'deck_b_id'=>102+$i]);$c=calc($ms);$tier=array_values(array_filter($c['metagame'],fn($r)=>$r['deck_id']===101))[0];
ck('QA-14',$tier['tier']==='SIN CLASIFICAR','5/5 remains unclassified',$tier['tier']);
ck('QA-15',ygo_stats_historical_tier([],1,'tcg',102,'2026-10-01')===null,'No inferred historic tier',null);
ck('QA-16',calc($ms)===calc($ms),'Deterministic replay',hash('sha256',json_encode(calc($ms))));
$c2=calc(array_replace($ms,[0=>sm(1,'B',['team_b_id'=>3,'deck_b_id'=>103])]));ck('QA-17',own($c2)['rating']!==own($c)['rating'],'Corrected old result changes replay',own($c2)['rating']);
ck('QA-20',count($ach)===39&&isset($ach['three_events']),'38 fixed plus dynamic',count($ach));
ck('QA-21',$ach['tier_zero_slayer']['name']==='Logro secreto'&&$ach['tier_zero_slayer']['target']===null,'Secret hides name and condition',$ach['tier_zero_slayer']['name']);
foreach(['bye','walkover','friendly'] as $key){$c=calc([sm(1,'A',[$key=>true])]);ck('QA-22-'.$key,count($c['ledger'])===0,'No artificial games or rating',count($c['ledger']));}
$analytics=ygo_stats_analytics(calc([sm(),sm(2,'E')]),1);$power=array_values(array_filter($analytics['rankings'],fn($r)=>$r['code']==='power5'))[0]['rows'][0]['value'];ck('QA-23',abs($power-66.6666666667)<.0001,'Power=(3+1)/6', $power);
$r=own(calc([sm()]));ck('QA-25',$r['fastest']===null&&$r['fewest_turns']===null,'Null duration/turns no records',[$r['fastest'],$r['fewest_turns']]);
ck('QA-26',str_contains($analytics['rankings'][14]['name'],'negativa'),'Brick labelled negatively',$analytics['rankings'][14]['name']);
ck('QA-37',count(ygo_stats_cuts('2026-01-01','2026-12-31','2026-01-15 00:00:00'))===1,'First cut day14',ygo_stats_cuts('2026-01-01','2026-12-31','2026-01-15 00:00:00'));
$m1=sm(1);$m2=sm(2,'B',['season_id'=>2]);$c=calc([$m1,$m2]);$sr=array_values(array_filter($c['metagame'],fn($r)=>$r['deck_id']===101&&$r['season_id']===2))[0];ck('QA-39',abs($sr['rating_season']-1484)<.0001,'New season base1500', $sr['rating_season']);
$c=calc([sm(),sm(2,'B',['format_id'=>'goat'])]);ck('QA-42',count($c['metagame'])===4&&count($c['global'])===4,'Separate formats',count($c['global']));
$raw=ygo_stats_metric(1,101,'tcg');$raw=array_replace($raw,['J'=>10,'V'=>7,'E'=>2,'D'=>1,'JD'=>30,'VG'=>20,'EG'=>2,'DG'=>8,'opponents'=>[2=>true,3=>true,4=>true,5=>true],'recent'=>[1,1,.5,0,1]]);$t=ygo_stats_tier($raw);ck('QA-43',abs($t['IC']-(.4*$t['R']+.25*$t['EL']+.2*$t['C']+.1*$t['RE']+.05*$t['RD']))<1e-10,'Exact weighted formula',$t['IC']);
$base=array_replace($raw,['J'=>10000,'V'=>10000,'E'=>0,'D'=>0,'JD'=>20000,'VG'=>20000,'EG'=>0,'DG'=>0,'rating_season'=>2500,'recent'=>[1,1,1,1,1],'opponents'=>[2=>true,3=>true,4=>true,5=>true,6=>true],'strong_n'=>3,'strong_score'=>3]);
foreach([44=>['V'=>7900,'E'=>2100],45=>['opponents'=>[2=>true,3=>true,4=>true]],46=>['strong_n'=>0,'strong_score'=>0]] as $id=>$changes){$t=ygo_stats_tier(array_replace($base,$changes));ck('QA-'.$id,$t['IC']>=90&&$t['tier']!=='Tier Zero','IC>=90 cannot bypass simultaneous Tier Zero requirements',$t);}
$t=ygo_stats_tier(array_replace($raw,['duels_complete'=>false]));ck('QA-47',$t['IC']===null&&$t['tier']==='SIN CLASIFICAR','Incomplete duels cannot impute RD',$t);
$cuts=ygo_stats_cuts('2026-01-01','2026-01-20','2026-02-01');ck('QA-49',count($cuts)===2&&$cuts[1]['final']===true,'Final off cadence included',$cuts);
$future=[['season_id'=>1,'format_id'=>'tcg','deck_id'=>102,'status'=>'PUBLICADO','published_at'=>'2026-10-10','tier'=>'Tier Zero']];ck('QA-51',ygo_stats_historical_tier($future,1,'tcg',102,'2026-10-09')===null,'Future tiers never retroactive',null);
$c=calc([sm()]);$r=own($c);ck('QA-59',$c['ledger'][0]['before_a']===1000.0,'Initial global 1000',$c['ledger'][0]['before_a']);
ck('QA-60',count($c['ledger'])===1,'One transaction per match',count($c['ledger']));
ck('QA-61',$r['rating']===1010.0&&own($c,2)['rating']===990.0,'Equal1000 K20 +10/-10',[$r['rating'],own($c,2)['rating']]);
$hi=ygo_stats_elo(1000,1200,1);$lo=ygo_stats_elo(1000,800,1);ck('QA-62',$hi['delta_a']>$lo['delta_a'],'Stronger opponent higher reward',[$hi['delta_a'],$lo['delta_a']]);
$hi=ygo_stats_elo(1000,1200,0);$lo=ygo_stats_elo(1000,800,0);ck('QA-63',abs($lo['delta_a'])>abs($hi['delta_a']),'Weaker loss greater deduction',[$hi['delta_a'],$lo['delta_a']]);
$draw=ygo_stats_elo(1000,1200,.5);ck('QA-64',$draw['delta_a']>0,'Draw depends on expectation',$draw['delta_a']);ck('QA-65',$draw['delta_a']+$draw['delta_b']===0.0,'Zero sum',$draw);
$c=calc([sm(1,'A',['competitive'=>null])]);ck('QA-67',count($c['ledger'])===0,'Unknown eligibility pending',$c['pending']);
foreach(['FULL'=>20,'BONUS'=>25] as $impact=>$k){$c=calc([sm(1,'A',['ranking_impact'=>$impact])]);ck('QA-69-'.$impact,$c['ledger'][0]['k']===$k*1.0,'Versioned K mapping',$c['ledger'][0]['k']);}
$ms=[];foreach(range(1,10) as $i)$ms[]=sm($i,'A',['team_b_id'=>2+$i%2,'deck_b_id'=>102+$i%2]);ck('QA-71',own(calc($ms))['state']==='CLASIFICADO','Two-match minimum does not require three rivals',own(calc($ms))['state']);
$ms[9]=sm(10,'A',['team_b_id'=>4,'deck_b_id'=>104]);ck('QA-72',own(calc($ms))['state']==='CLASIFICADO','10 matches vs3 qualifies',own(calc($ms))['state']);
$c=ygo_stats_calculate($ms,[],[],[],'2027-05-01');ck('QA-73',own($c)['state']==='INACTIVO','Inactive preserves rating',own($c)['state']);
foreach(range(1,3) as $i){$ms[]=sm(10+$i,'A',['played_at'=>'2027-05-0'.$i,'team_b_id'=>4,'deck_b_id'=>104]);$c=ygo_stats_calculate($ms,[],[],[],'2027-05-04');ck('QA-74-'.$i,own($c)['state']===($i<3?'REACTIVACION PENDIENTE':'CLASIFICADO'),'Three matches reactivate',own($c)['state']);}
$c=calc([sm(),sm()]);ck('QA-80',count($c['ledger'])===1,'Duplicate match idempotent',count($c['ledger']));
$c=calc([sm(2,'B',['played_at'=>'2026-10-01']),sm(1,'A',['played_at'=>'2026-10-01'])]);ck('QA-81',$c['ledger'][0]['match_id']===1,'Tie dates stable ID',$c['ledger'][0]['match_id']);
$events=[];foreach(['friendly','cup','world','league'] as $i=>$type)$events[]=['id'=>$i+1,'name'=>$type,'type'=>$type,'format_id'=>'tcg','season_id'=>1];$honors=[];foreach($events as $e)$honors[]=['competition_id'=>$e['id'],'champion_team_id'=>1,'champion_deck_id'=>101,'confirmed'=>true,'effective_at'=>'2026-10-01'];
$c=calc([],$events,$honors);ck('QA-87',count($c['medals'])===3,'One per nonfriendly',count($c['medals']));ck('QA-88',!array_filter($c['medals'],fn($m)=>$m['event_id']===1),'No friendly medal',$c['medals']);
$pending=calc([],$events,[]);ck('QA-89',!array_filter($pending['medals'],fn($m)=>$m['status']!=='PENDIENTE DE ENTREGA'),'Unknown champion pending',$pending['medals']);
foreach([2=>30,3=>50,4=>25] as $id=>$bonus){$med=array_values(array_filter($c['medals'],fn($m)=>$m['event_id']===$id))[0];ck('QA-'.(88+$id),$med['bonus_points']===$bonus,'Bonus by type',$med['bonus_points']);}
$r=own($c);ck('QA-95',$r['rating']===1000.0,'Bonuses do not alter Elo',$r['rating']);ck('QA-96',$r['total']===1105.0,'Total=1000+30+50+25',$r['total']);ck('QA-97',$r['state']==='PROVISIONAL','Medals do not bypass sample',$r['state']);
$events[3]['format_id']='goat';$c=calc([],$events,$honors);ck('QA-99',own($c,1,'goat')['total']===1025.0&&own($c)['total']===1080.0,'No cross format bonus',[own($c,1,'goat')['total'],own($c)['total']]);
$honors[3]['champion_team_id']=2;$c=calc([],$events,$honors);ck('QA-100',own($c,2,'goat')['bonus_medallas']===25,'Champion correction reassigns bonus',own($c,2,'goat')['bonus_medallas']);
$events[3]['fixed_bonus']=99;$c=calc([],$events,$honors);ck('QA-103',own($c,2,'goat')['bonus_medallas']===99,'Edition fixed bonus overrides later policy',own($c,2,'goat')['bonus_medallas']);
ck('selectors',count($analytics['rankings'])===18&&count($analytics['tops'])===19,'18 selectors and19 tops',[count($analytics['rankings']),count($analytics['tops'])]);
$copy=sm();$copy['archetype']='Same name';$c=calc([$copy,sm(2,'B',['deck_a_id'=>151,'archetype'=>'Same name'])]);
ck('QA-30',count($c['metagame'])===3,'Archetype does not merge deck IDs',count($c['metagame']));
ck('QA-85',count($c['global'])===2&&own($c)['deck_id']===151&&$c['ledger'][0]['deck_a_id']===101,'Team continuity and historic deck identity',own($c)['deck_id']);
$m=sm();$m['duels'][0]['player_a_id']=71;$m['duels'][1]['player_a_id']=72;$c=calc([$m]);ck('QA-19',count($c['players'])===2&&$c['players'][0]['JD']===1,'Players receive only their own duels',$c['players']);
$events=[['id'=>10,'name'=>'Official edition','type'=>'cup','format_id'=>'tcg','season_id'=>1]];$honors=[['competition_id'=>10,'champion_team_id'=>1,'champion_deck_id'=>101,'runner_up_team_id'=>2,'confirmed'=>true,'effective_at'=>'2026-10-01']];
$c=calc([sm(),sm(2,'B',['event_id'=>20])],$events,$honors);$ach=array_column(own($c)['achievements'],null,'code');ck('QA-12',$ach['undefeated_champion']['status']==='DESBLOQUEADO','Invincible within title event, external loss allowed',$ach['undefeated_champion']['status']);
ck('QA-94',own($c,2)['bonus_medallas']===0,'Runner-up no medal',own($c,2)['bonus_medallas']);
$a=calc([sm()]);$b=calc([sm()],$events,$honors);ck('QA-68',$a['ledger']===$b['ledger'],'Title bonus leaves match ledger unchanged',$b['ledger'][0]['delta_a']);
$rankings=ygo_stats_analytics($b,1)['rankings'];$ratingRows=$rankings[1]['rows'];ck('QA-84',$ratingRows[0]['value']===own($b)['rating']&&$ratingRows[0]['value']!==own($b)['total'],'Selector02 is Elo component, not total',$ratingRows[0]['value']);
$p=ygo_stats_calculate([sm()],[],[],[], '2027-10-30');ck('QA-52',own($p)['rating']===own($a)['rating'],'No inactivity rating penalty',own($p)['rating']);
ck('QA-56',ygo_stats_tier(ygo_stats_metric(1,101,'tcg'))['tier']==='SIN CLASIFICAR'&&own($a)['rating']===1010.0,'Tier calculation cannot change global rating',own($a)['rating']);
$first=sm();$first['appearance_count']=100000;$second=sm();ck('QA-13',calc([$first])['metagame']===calc([$second])['metagame'],'Popularity has weight zero',true);
$first['editorial_score']=100;ck('QA-33',calc([$first])['global']===calc([$second])['global'],'Editorial scoring cannot alter results',true);
$data=calc([sm(1,'A',['official_points_a'=>7,'official_points_b'=>2])]);ck('QA-28',own($data)['official_points']===7,'Read official points literally, not3/1/0',own($data)['official_points']);
$orig=json_encode([$events,$honors,$data]);calc([sm()],$events,$honors);ck('QA-27',json_encode([$events,$honors,$data])===$orig,'Pure statistics never modifies official input',true);ck('QA-86',count($honors)===1&&$honors[0]['champion_team_id']===1,'Statistical ordering never changes champion',$honors[0]['champion_team_id']);
$data=calc([sm(),sm(2)]);$r=own($data);$opponentPrior=$data['ledger'][0]['before_b']+$data['ledger'][1]['before_b'];ck('QA-24',$r['rival_pre_sum']===$opponentPrior&&$r['rival_pre_sum']/2!==own($data,2)['rating'],'Opponent strength uses prior ratings',$r['rival_pre_sum']);
$data=calc([sm(1,'A',['duels'=>[['position'=>1,'winner'=>'A','starts_player'=>'A'],['position'=>2,'winner'=>'A']]])]);$rate=ygo_stats_analytics($data,1)['rankings'][10]['rows'][0];ck('QA-32',$rate['sample']===1&&$rate['coverage']===50.0,'Partial coverage uses observed unit denominator',$rate);
ck('QA-35',$data['ledger'][0]['played_at']==='2026-10-01 19:00:00'&&$data['cutoff']!=='2026-10-01 19:00:00','Sporting and calculation dates distinct',[$data['ledger'][0]['played_at'],$data['cutoff']]);
$data=calc([sm(1),sm(2,'A',['season_id'=>2])]);ck('QA-40',count(array_unique(array_column($data['metagame'],'season_id')))===2,'Season history retained',array_column($data['metagame'],'season_id'));
ck('QA-75',$data['ledger'][1]['before_a']===$data['ledger'][0]['after_a'],'Global Elo survives season boundary',$data['ledger'][1]['before_a']);ck('QA-76',array_values(array_filter($data['metagame'],fn($r)=>$r['season_id']===2&&$r['deck_id']===101))[0]['rating_season']===1516.0,'Seasonal Elo resets independently',true);
$data=calc([sm(1,'A',['format_id'=>'goat']),sm(2,'B',['format_id'=>'tcg'])]);ck('QA-58',own($data,1,'goat')['rating']===1010.0&&own($data,1,'tcg')['rating']===990.0,'No transfer across formats',true);
$data=calc([sm(1,'A',['friendly'=>true]),sm(2,'A',['ranking_impact'=>'NONE']),sm(3,'A',['cancelled'=>true])]);ck('QA-41',count($data['ledger'])===0,'Only confirmed eligible StationX games',count($data['ledger']));ck('QA-66',count($data['global'])===0,'Excluded games do not create global accounts',count($data['global']));
$games=[];foreach(range(1,5) as $i)$games[]=sm($i,'A',['team_b_id'=>2+$i,'deck_b_id'=>102+$i]);ck('QA-70',own(calc($games))['state']==='CLASIFICADO','Five confirmed matches exceed the two-match minimum',own(calc($games))['state']);
ck('QA-54',calc($games)===calc(array_reverse($games)),'Reproducible by sporting time and ID',true);
$events=[];$honors=[];foreach([1=>10,2=>10,3=>1] as $id=>$team){$events[]=['id'=>$id,'name'=>'Edition '.$id,'type'=>'tournament','format_id'=>'tcg','fixed_bonus'=>$id===3?40:20];$honors[]=['competition_id'=>$id,'champion_team_id'=>$team,'champion_deck_id'=>100+$team,'confirmed'=>true,'effective_at'=>'2026-10-01'];}
$data=calc([],$events,$honors);ck('QA-82',$data['global'][0]['team_id']===10,'Equal totals and Elo: championship count before team ID',$data['global'][0]['team_id']);
ck('QA-93',count(array_filter($data['medals'],fn($m)=>$m['team_id']===10))===2,'Two editions produce two medals',2);
ck('QA-101',$data===calc([],$events,$honors),'Replay does not duplicate awards',true);
$events[0]['format_id']=null;$data=calc([],$events,$honors);ck('QA-102',$data['medals'][0]['status']==='PENDIENTE DE ENTREGA','No bonus when format unknown',$data['medals'][0]['status']);
$data=calc([sm(1,'A',['ranking_impact'=>'NONE'])],[['id'=>10,'name'=>'League','type'=>'league','format_id'=>'tcg']], [['competition_id'=>10,'champion_team_id'=>1,'champion_deck_id'=>101,'confirmed'=>true,'effective_at'=>'2026-10-01']]);ck('QA-98',count($data['ledger'])===0&&own($data)['bonus_medallas']===25,'NONE games no Elo, official title still medal',own($data)['total']);
$data=calc([sm(),sm(2,'A',['event_id'=>20]),sm(3,'A',['event_id'=>30])]);$ach=array_column(own($data)['achievements'],null,'code');ck('QA-106',$ach['three_events']['status']==='DESBLOQUEADO','Three distinct winning eventIDs',$ach['three_events']['progress']);
ck('QA-107',own($data)['bonus_medallas']===0,'Achievements grant no global bonus',own($data)['bonus_medallas']);
ck('QA-104',count(ygo_medal_definitions())===38&&count($ach)===39,'38 fixed plus format achievement, separate resource',count($ach));ck('QA-105',count($data['medals'])===0,'Gold/silver achievements create no championship medals',count($data['medals']));
$data=calc([],[['id'=>10,'name'=>'Special','type'=>'special','format_id'=>'tcg']],[['competition_id'=>10,'champion_team_id'=>1,'champion_deck_id'=>101,'confirmed'=>true,'effective_at'=>'2026-10-01']]);ck('QA-113',own($data)['bonus_medallas']===20,'Special default bonus20',own($data)['bonus_medallas']);
$data=calc([],[['id'=>10,'name'=>'Future','type'=>'cup','format_id'=>'tcg']],[]);ck('QA-109',count($data['global'])===0,'Unawarded medal contributes no points',count($data['global']));ck('QA-29',$data['medals'][0]['status']==='PENDIENTE DE ENTREGA','Insufficient official data pending',$data['medals'][0]['status']);
$data=calc([sm(),sm(2,'B')]);$data['global'][0]['WR']=50.00001;$data['global'][1]['WR']=50.00002;$ranked=ygo_stats_analytics($data,1)['rankings'][7]['rows'];ck('QA-04',$ranked[0]['value']===50.00002,'Sort exact values even if displayed equal',$ranked[0]['value']);
$cuts=[['season_id'=>1,'format_id'=>'tcg','deck_id'=>102,'status'=>'PUBLICADO','published_at'=>'2026-09-30','tier'=>'Tier Zero'],['season_id'=>1,'format_id'=>'goat','deck_id'=>102,'status'=>'PUBLICADO','published_at'=>'2026-09-30','tier'=>'Tier 1']];$data=ygo_stats_calculate([sm()],[],[],[],'2026-10-30',$cuts);$ach=array_column(own($data)['achievements'],null,'code');ck('QA-34',$ach['tier_zero_slayer']['status']==='DESBLOQUEADO','Historic same-season same-format tier only',$ach['tier_zero_slayer']['status']);
$events=[['id'=>10,'name'=>'Cup edition','type'=>'cup','format_id'=>'tcg']];$honors=[['competition_id'=>10,'champion_team_id'=>1,'champion_deck_id'=>101,'confirmed'=>true,'effective_at'=>'2026-10-01']];$data=calc([sm(1),sm(2,'A',['season_id'=>2])],$events,$honors);ck('QA-108',own($data)['bonus_medallas']===30,'Medal bonus persists across seasons',own($data)['bonus_medallas']);
$events=[['id'=>20,'name'=>'Higher bonus','type'=>'world','format_id'=>'tcg']];$honors=[['competition_id'=>20,'champion_team_id'=>2,'champion_deck_id'=>102,'confirmed'=>true,'effective_at'=>'2026-10-01']];$data=calc([sm()],$events,$honors);ck('QA-110',$data['global'][0]['team_id']===2&&$data['global'][0]['rating']<own($data)['rating'],'Order by total, not Elo component',$data['global'][0]['total']);
$export=ygo_stats_export_rows($data);$headers=$export[0];$row=array_combine($headers,$export[1]);ck('QA-111',$row['rating']===$data['global'][0]['rating']&&$row['total']===$data['global'][0]['total'],'CSV and PDF consume identical snapshot projection',$row);ck('QA-55',$row['version']===$data['version']&&$row['corte']===$data['cutoff'],'Export carries exact version and cut',[$row['version'],$row['corte']]);
$reportDir=dirname(__DIR__,2).'/outputs/station-x-aplicacion-integral-20261017';
if (!is_dir($reportDir)) mkdir($reportDir,0777,true);
file_put_contents($reportDir.'/statistics-unit.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo 'PASS '.$n.' statistical acceptance assertions'.PHP_EOL;
