<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class AdmissionQuotaPolicy
{
    public static function quotas(array $rows,int $capacity): array
    {
        if(!$rows || !array_is_list($rows) || count($rows)>32 || $capacity<0 || $capacity>64)throw new InvalidArgumentException('Configura entre 1 y 32 fuentes y un total de hasta 64 plazas.');
        foreach($rows as $r)if(!is_array($r))throw new InvalidArgumentException('Fuente invalida.');
        $percentage=count(array_filter($rows,static fn($r)=>($r['mode']??'')==='percentage'));
        if($percentage && $percentage!==count($rows))throw new InvalidArgumentException('Usa porcentajes en todas las fuentes o cupos/campeones; no se pueden mezclar ambos sistemas.');
        $quotas=[];$sum=0;$remainders=[];
        foreach($rows as $i=>$r){
            $mode=$r['mode']??'';$v=filter_var($r['value']??0,FILTER_VALIDATE_INT);
            if(!in_array($mode,['winner','runner_up','top_n','percentage','minimum_points','best_record','group_position'],true) || (!in_array($mode,['winner','runner_up'],true) && ($v===false || $v<1 || $v>($percentage?100:64))))throw new InvalidArgumentException('Los cupos y porcentajes deben ser enteros positivos.');
            $sum+=in_array($mode,['winner','runner_up'],true)?1:$v;
            $quotas[$i]=$percentage?intdiv($capacity*$v,100):(in_array($mode,['winner','runner_up'],true)?1:$v);
            if($percentage)$remainders[$i]=($capacity*$v)%100;
        }
        if($percentage){
            if($sum!==100 || $capacity<2)throw new InvalidArgumentException('Los porcentajes deben sumar 100 y el total debe ser al menos 2.');
            $order=array_keys($remainders);usort($order,static fn($a,$b)=>$remainders[$b]<=>$remainders[$a] ?: $a<=>$b);
            foreach(array_slice($order,0,$capacity-array_sum($quotas)) as $i)$quotas[$i]++;
        }elseif($capacity!==0 && array_sum($quotas)!==$capacity)throw new InvalidArgumentException('La suma de cupos debe coincidir exactamente con el total de plazas.');
        if(array_sum($quotas)<2 || array_sum($quotas)>64)throw new InvalidArgumentException('La admision requiere entre 2 y 64 clasificados.');
        return $quotas;
    }
}
