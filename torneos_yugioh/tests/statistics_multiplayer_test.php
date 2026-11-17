<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/statistics_v8.php';
require dirname(__DIR__).'/includes/statistics_multiplayer_source.php';
require dirname(__DIR__).'/includes/competitive_intelligence.php';
$n=0;function mp(bool $ok,string $why):void{global $n;$n++;if(!$ok)throw new RuntimeException($why);}
function near(float $a,float $b):bool{return abs($a-$b)<1e-8;}
$identity=static fn(int $id):array=>['deck_id'=>$id,'team_id'=>$id,'format_id'=>'tcg_advanced','quality'=>'CONFIRMED_AT_ROSTER_FREEZE'];
$tag=['kind'=>'tag','source_key'=>'match:10:tag','match_id'=>10,'event_id'=>1,'season_id'=>1,'format_id'=>'tcg_advanced','confirmed'=>true,'competitive'=>true,'friendly'=>false,'played'=>true,'played_at'=>'2026-10-18 19:00:00','result'=>'A','ranking_impact'=>'FULL','participants_a'=>[$identity(1),$identity(2)],'participants_b'=>[$identity(3),$identity(4)],'duels'=>[['position'=>1,'winner'=>'A'],['position'=>2,'winner'=>'A']],'source_version'=>'v1','team_a_id'=>0,'team_b_id'=>0,'deck_a_id'=>0,'deck_b_id'=>0];
$out=ygo_stats_calculate([$tag],[],[],[],'2026-10-19 00:00:00');$rows=array_column($out['global'],null,'team_id');
mp(count($rows)===4,'four actual Tag identities');mp(count($out['ledger'])===1,'one ledger per real collective match');
foreach([1,2,3,4] as $id){mp(near($rows[$id]['rating'],$id<3?1010:990),'team-average Elo delta');mp($rows[$id]['J']===1,'once per participant');mp($rows[$id]['JD']===0&&$rows[$id]['VG']===0,'no shared duel wins invented as individual');mp(count($rows[$id]['opponents'])===2,'two actual opposing players');}
mp(near(array_sum(array_column($rows,'rating')),4000),'Tag zero sum');mp(count($out['metagame'])===0,'shared Tag games never fabricate individual metagame duels');
mp(count(ygo_stats_filter([$tag],['team_id'=>'4']))===1,'Tag noncaptain included by filters');
$corrected=array_replace($tag,['result'=>'B','source_version'=>'v2']);$r=ygo_stats_calculate([$corrected],[],[],[],'2026-10-19 00:00:00');mp(near(array_column($r['global'],null,'team_id')[1]['rating'],990),'corrected collective result replays');
$duplicate=ygo_stats_calculate([$tag,$tag],[],[],[],'2026-10-19 00:00:00');mp(count($duplicate['ledger'])===1,'duplicate Tag fact idempotent');
$bad=$tag;$bad['participants_b'][0]['team_id']=1;mp(ygo_stats_calculate([$bad],[],[],[],'2026-10-19 00:00:00')['global']===[],'duplicate competitive identity rejects whole group atomically');
$draw=array_replace($tag,['result'=>'E']);$r=ygo_stats_calculate([$draw],[],[],[],'2026-10-19 00:00:00');foreach($r['global'] as $row)mp(near($row['rating'],1000)&&$row['E']===1,'draw updates all four correctly');
$db=['id'=>20,'result'=>'A','score'=>'2-1','deck_a_id'=>1,'deck_c_id'=>2,'deck_e_id'=>3,'deck_b_id'=>4,'deck_d_id'=>5,'deck_f_id'=>6];
$record=['version'=>'STATION_X_TEAMS_V1','kind'=>'team','revision'=>1,'lineup_a'=>['decks'=>[1,2,3]],'lineup_b'=>['decks'=>[4,5,6]],'identities'=>array_combine(range(1,6),array_map($identity,range(1,6))),'aggregate'=>['winner'=>'A','score'=>'2-1'],'sequences'=>[['A','A'],['B','B'],['A','A']],'evaluations'=>[['winner'=>'A','status'=>'FINALIZADA'],['winner'=>'B','status'=>'FINALIZADA'],['winner'=>'A','status'=>'FINALIZADA']]];
$base=array_replace($tag,['match_id'=>20,'source_key'=>'match:20','kind'=>'team_seat']);unset($base['participants_a'],$base['participants_b']);
$seats=ygo_stats_team_source($db,$base,$record);mp(count($seats)===3,'three real seat subresults');mp(count(array_unique(array_column($seats,'source_key')))===3,'distinct stable seat keys, same source match');
$r=ygo_stats_calculate($seats,[],[],[],'2026-10-19 00:00:00');mp(count($r['ledger'])===3&&count($r['global'])===6,'all seats counted once without aggregate duplication');$byTeam=array_column($r['global'],null,'team_id');mp($byTeam[2]['D']===1&&$byTeam[5]['V']===1,'losing side seat winner keeps actual individual win');
$badRecord=$record;unset($badRecord['identities']);$pending=ygo_stats_team_source($db,$base,$badRecord);mp($pending[0]['team_a_id']===0,'unknown historical identity not inferred from current deck');
$badRecord=$record;$badRecord['aggregate']['winner']='B';mp(ygo_stats_team_source($db,$base,$badRecord)[0]['team_a_id']===0,'contradictory aggregate rejected');
foreach([3,4] as $size){$ratings=array_fill_keys(range(1,$size),1000);$change=ygo_stats_ffa_elo($ratings,1);mp(near(array_sum(array_column($change,'delta')),0),'FFA zero sum');mp(near($change[1]['delta'],20*(1-1/$size)),'winner-only expected gain');foreach(range(2,$size) as $id)mp(near($change[$id]['delta'],-20/$size),'each loser normalized by real table size');}
$skilled=ygo_stats_ffa_elo([1=>1600,2=>1000,3=>1000,4=>1000],1);mp($skilled[1]['delta']<15,'favorite winner earns less');
$m=['match_id'=>1,'event_id'=>3,'season_id'=>1,'format_id'=>'tcg_advanced','played_at'=>'2026-10-18 19:00:00','confirmed'=>true,'competitive'=>true,'winner'=>1,'players'=>array_map($identity,range(1,4)),'source_version'=>'v1'];
$ffa=ygo_stats_calculate_ffa([$m],[],'2026-10-19 00:00:00');mp(count($ffa['ranking'])===4&&count($ffa['ledger'])===1,'FFA native table not pairwise fabricated matches');mp(near($ffa['ranking'][0]['rating'],1015),'four-player equal Elo result');
$same=ygo_stats_calculate_ffa([$m,$m],[],'2026-10-19 00:00:00');mp($same===$ffa,'FFA duplicate idempotence');
$fixed=array_replace($m,['winner'=>4,'source_version'=>'v2']);$rev=ygo_stats_calculate_ffa([$fixed],[],'2026-10-19 00:00:00');mp($rev['ranking'][0]['deck_id']===4,'FFA correction recalculates winner');
$future=ygo_stats_calculate_ffa([$m],[],'2026-10-01 00:00:00');mp($future['ranking']===[],'cut excludes later matches');
$unknown=$m;unset($unknown['players'][0]['quality']);mp(ygo_stats_calculate_ffa([$unknown],[],'2026-10-19 00:00:00')['ranking']===[],'FFA no retrospective identity guessing');
$mixed=$m;$mixed['players'][1]['format_id']='goat';mp(ygo_stats_calculate_ffa([$mixed],[],'2026-10-19 00:00:00')['ranking']===[],'FFA rejects mixed formats');
$many=[];for($i=1;$i<=10;$i++)$many[]=array_replace($m,['match_id'=>$i]);$rank=ygo_stats_calculate_ffa($many,[],'2026-10-19 00:00:00');mp($rank['ranking'][0]['state']==='CLASIFICADO'&&$rank['ranking'][0]['position']===1,'FFA ten real tables and three opponents qualifies');
$inactive=ygo_stats_calculate_ffa($many,[],'2027-03-01 00:00:00');mp($inactive['ranking'][0]['state']==='INACTIVO','FFA inactivity');
$cross=array_replace($m,['match_id'=>11,'season_id'=>2,'played_at'=>'2027-03-01 19:00:00']);$continued=ygo_stats_calculate_ffa(array_merge($many,[$cross]),[],'2027-03-02 00:00:00');mp($continued['ranking'][0]['J']===11&&$continued['ranking'][0]['rating']>1015,'permanent FFA Elo survives season change');mp($continued['ranking'][0]['state']==='REACTIVACION PENDIENTE','FFA three activity matches needed after inactivity');
$data=ygo_stats_calculate([$tag],[],[],[],'2026-10-19 00:00:00',[],[$m]);mp(count($data['global'])===4&&count($data['free_for_all']['ranking'])===4,'separate global and FFA domains');
mp(count(ygo_stats_export_rows($data,'ffa_ranking'))===5,'FFA ranking common CSV PDF projection');mp(count(ygo_stats_export_rows($data,'ffa_ledger'))===5,'FFA changes export tied to one source table');mp(is_string(ygo_stats_export_rows($data,'ledger')[1][count(ygo_stats_export_rows($data,'ledger')[0])-4]),'Tag participants serialized for CSV');
echo 'PASS '.$n.' team and multiplayer ranking assertions'.PHP_EOL;
