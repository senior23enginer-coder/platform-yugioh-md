<?php
declare(strict_types=1);

function ygo_completion_now(): string {
    return (new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
}

/** Server-owned close dates. Existing results without evidence stay undated. */
function ygo_completion_games(array $incoming,array $stored,array $before,array $after,string $now,bool $allowNew=true): array {
    $old=[];$rows=[];
    foreach($stored as $row)if(is_array($row))$old[(int)($row['duel']??0)]=$row;
    foreach($incoming as $row)if(is_array($row))$rows[(int)($row['duel']??0)]=$row;
    foreach($after as $i=>$winner){
        $n=$i+1;$row=array_replace($old[$n]??[], $rows[$n]??['duel'=>$n]);
        unset($row['completed_at']);
        if(!empty($old[$n]['completed_at']))$row['completed_at']=$old[$n]['completed_at'];
        elseif($allowNew&&!in_array($before[$i]??'', ['A','B','E'],true)&&in_array($winner,['A','B','E'],true))$row['completed_at']=$now;
        $rows[$n]=$row;
    }
    ksort($rows);return array_values($rows);
}

/** Called inside the result transaction, before changing the match status. */
function ygo_completion_set_status(PDO $p,int $id,string $status,?string $now=null): void {
    $p->prepare("UPDATE sx_ygo_matches SET completed_at=CASE
        WHEN ?='FINALIZADA' AND UPPER(status) NOT IN ('FINALIZADA','FINALIZADO','COMPLETED','FINISHED')
        THEN COALESCE(completed_at,?) ELSE completed_at END, status=? WHERE id=?")
        ->execute([$status,$now??ygo_completion_now(),$status,$id]);
}
