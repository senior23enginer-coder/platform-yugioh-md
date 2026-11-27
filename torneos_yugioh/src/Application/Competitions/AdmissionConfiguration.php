<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Competitions;

use InvalidArgumentException;
use StationX\Yugioh\Domain\Competitions\AdmissionConfigurationPolicy;
use StationX\Yugioh\Domain\Competitions\AdmissionDependencyPolicy;

final readonly class AdmissionConfiguration
{
    public function __construct(private AdmissionConfigurationStore $store, private AdmissionSourceReader $reader, private ResolveEventAdmission $resolver) {}

    public function requireParticipants(array $c, array $ids, bool $freeze = true): array
    {
        $a=$this->store->load($c,true);
        if(!$a){if(AdmissionConfigurationPolicy::required($c))throw new InvalidArgumentException('Configura Fuentes y clasificados en la ficha y carga sus plazas antes de programar.');return $ids;}
        AdmissionDependencyPolicy::assertNoCycle((int)$c['id'],$a['sources'],$this->reader->edges());
        $resolved=$this->resolver->execute($c,$a,true);
        if(!empty($a['frozen']) && $a['selection']!==$resolved)throw new InvalidArgumentException('Una fuente cambio despues de programar; se conserva el evento sin recalcular sus plazas.');
        if(empty($a['frozen']) && $a['selection']!==$resolved)throw new InvalidArgumentException('Una fuente cambio desde la carga. Guarda Fuentes y clasificados para revisar y cargar nuevamente sus plazas.');
        $posted=array_map('intval',$ids);$expected=$resolved['ids'];sort($posted);sort($expected);
        if($posted!==$expected)throw new InvalidArgumentException('Los participantes deben coincidir exactamente con los clasificados de las fuentes configuradas.');
        if($freeze){$a['frozen']=true;$a['selection']=$resolved;$this->store->store($c,$a);}
        return $resolved['ids'];
    }

    public function view(array $c): array
    {
        $a=$this->store->load($c);if(!$a)return ['config'=>null,'ok'=>!AdmissionConfigurationPolicy::required($c),'message'=>AdmissionConfigurationPolicy::required($c)?'Falta configurar fuentes y clasificados.':'Inscripcion directa.'];
        try{$selection=$this->resolver->execute($c,$a);if($selection!==$a['selection'])throw new InvalidArgumentException(!empty($a['frozen'])?'Cambio una fuente ya utilizada.':'La fuente cambio: revisa y guarda Fuentes y clasificados para cargar sus plazas nuevamente.');return ['config'=>$a,'ok'=>true,'message'=>count($selection['ids']).' clasificados verificados.','selection'=>$selection];}
        catch(InvalidArgumentException $e){return ['config'=>$a,'ok'=>false,'message'=>$e->getMessage()];}
    }
}
