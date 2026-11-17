<?php
declare(strict_types=1);
require __DIR__.'/../includes/competitive_intelligence.php';
function rankingMatch(int $id): array {return ['match_id'=>$id,'event_id'=>10,'season_id'=>1,'format_id'=>'tcg','confirmed'=>true,'competitive'=>true,'result'=>'A','ranking_impact'=>'FULL','played_at'=>'2026-11-'.sprintf('%02d',$id).' 19:00:00','team_a_id'=>1,'team_b_id'=>2,'deck_a_id'=>101,'deck_b_id'=>102,'duels_complete'=>true,'duels'=>[['position'=>1,'winner'=>'A'],['position'=>2,'winner'=>'A']]];}
function teamOne(array $data): array {return array_values(array_filter($data['global'],fn($r)=>$r['team_id']===1))[0];}
function checkRanking(bool $ok): void {if(!$ok)throw new RuntimeException('Two match ranking contract failed');}
$one=ygo_stats_calculate([rankingMatch(1)],[],[],[],'2026-11-13 12:00:00');
checkRanking(teamOne($one)['position']===null&&teamOne($one)['state']==='PROVISIONAL');
$two=ygo_stats_calculate([rankingMatch(1),rankingMatch(2)],[],[],['minimum_matches'=>10,'minimum_opponents'=>3],'2026-11-13 12:00:00');
$row=teamOne($two);checkRanking($row['J']===2&&count($row['opponents'])===1&&$row['position']===1&&$row['state']==='CLASIFICADO');
checkRanking($two['policy']['minimum_matches']===2&&$two['policy']['minimum_opponents']===1);
checkRanking($two['policy']['meta_minimum_matches']===10&&$two['policy']['meta_minimum_opponents']===3);
$partial=rankingMatch(3);$partial['confirmed']=false;
$replay=ygo_stats_calculate([rankingMatch(1),rankingMatch(2),$partial],[],[],[],'2026-11-13 12:00:00');
checkRanking(teamOne($replay)['J']===2&&teamOne($replay)['rating']===$row['rating']);
$deleted=ygo_stats_calculate([rankingMatch(1)],[],[],[],'2026-11-13 12:00:00');checkRanking(teamOne($deleted)['position']===null);
$expected=ygo_stats_elo(1000,1000,1,20,600);$expected2=ygo_stats_elo($expected['after_a'],$expected['after_b'],1,20,600);
checkRanking(abs($row['rating']-$expected2['after_a'])<1e-10);
echo "OK: 1 match unranked, 2 matches ranked against the same rival, legacy threshold replaced, Elo unchanged, partial excluded and correction recalculated.\n";
