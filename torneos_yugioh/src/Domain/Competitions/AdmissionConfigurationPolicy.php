<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Competitions;

use InvalidArgumentException;

final class AdmissionConfigurationPolicy
{
    public static function required(array $c): bool
    {
        return QualificationPolicy::mode((string)($c['advancement_rule']??''))!==null || ($c['participant_source']??'')==='PREVIOUS_EVENT' || in_array($c['execution_model']??'',['top_cut','playoffs'],true);
    }

    public static function encode(array $admission): string
    {
        $json=json_encode($admission,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if(strlen($json)>65535)throw new InvalidArgumentException('La admision excede la capacidad permitida.');
        return $json;
    }

    public static function assertResultEditable(int $source, array $configurations): void
    {
        foreach($configurations as $v){$a=json_decode((string)$v,true);if(!empty($a['frozen']) && in_array($source,array_map('intval',array_column($a['sources'],'source_id')),true))throw new InvalidArgumentException('Este resultado ya clasifico decks a otro evento programado. Primero se requiere revertir el evento dependiente.');}
    }
}
