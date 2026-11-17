<?php
declare(strict_types=1);

/** Cup progress belongs to the first unfinished generated round; league progress spans its whole fixture. */
function ygo_event_progress(array $rounds, bool $perRound): array
{
    usort($rounds,static fn($a,$b)=>(int)$a['round_number']<=>(int)$b['round_number']);
    $done=0;$total=0;$round=null;
    if($perRound&&$rounds){
        $current=end($rounds);
        foreach($rounds as $row)if((int)$row['done']<(int)$row['total']){$current=$row;break;}
        $done=(int)$current['done'];$total=(int)$current['total'];$round=(int)$current['round_number'];
    }else foreach($rounds as $row){$done+=(int)$row['done'];$total+=(int)$row['total'];}
    return ['done'=>$done,'total'=>$total,'round'=>$round,'ratio'=>$total>0?$done/$total:0];
}

function ygo_event_progress_groups(PDO $pdo): array
{
    $groups=[];
    $rows=$pdo->query("SELECT competition_id,round_number,COUNT(*) AS total,SUM(CASE WHEN UPPER(TRIM(status)) IN ('FINALIZADA','FINALIZADO','COMPLETED') THEN 1 ELSE 0 END) AS done FROM sx_ygo_matches WHERE competition_id IS NOT NULL GROUP BY competition_id,round_number")->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row)$groups[(int)$row['competition_id']][]=$row;
    return $groups;
}
