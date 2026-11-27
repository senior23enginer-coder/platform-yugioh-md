<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class AdmissionDependencyPolicy
{
    public static function assertNoCycle(int $target, array $sources, array $rows): void
    {
        $edges=[];foreach($rows as $r)$edges[(int)$r['competition_id']][]=(int)$r['depends_on_competition_id'];
        $edges[$target]=array_map(static fn($r)=>(int)$r['source_id'],$sources);$stack=$edges[$target];$visited=[];
        while($stack){$id=array_pop($stack);if($id===$target)throw new InvalidArgumentException('Las fuentes forman una dependencia circular.');if(isset($visited[$id]))continue;$visited[$id]=true;foreach($edges[$id]??[] as $next)$stack[]=$next;}
    }
}
