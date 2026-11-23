<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/decks/data-package.php';
function a01_assert(bool $value,string $label): void { if(!$value)throw new RuntimeException($label); }
function a01_reject(array $record,string $reason): void {
    try{ ygo_data_validate_a01_profile($record); }
    catch(InvalidArgumentException $e){return;}
    throw new RuntimeException('A01 should reject '.$reason);
}
$profile=[
 'Arquetipo'=>'Nexo Umbral',
 'Tipo de estrategia'=>'Combo de Fusión y Cementerio',
 'Estilo breve'=>'Prepara recursos en el Cementerio y desarrolla Fusiones para mantener presión.',
 'Resumen para ficha'=>'Inicia con cartas válidas, convierte recursos en materiales de Fusión y obtiene ventaja condicional.',
 'Cómo se juega'=>'Primero conserva recursos; segundo verifica amenazas y materiales. En juego tardío recupera cartas solo si se cumplen las condiciones acreditadas.',
 'Qué vigilar'=>'Pérdida de recursos e interrupciones de las Invocaciones.',
];
$record=[
 'schema'=>'station-x-curated-v62','deck_id'=>13579,'format'=>'tcg_advanced',
 'name'=>'Nexo Umbral',
 'a01_profile_contract'=>'station-x-a01-profile/1','profile'=>$profile,
 'cards'=>[
   ['id'=>'13579:CARD:1','cid'=>1111,'name'=>'Uno','fields'=>[],
    'slots'=>[['card_id'=>1,'section'=>'main','quantity'=>1]],
    'sources'=>[],'synergy'=>['labels'=>[]]],
   ['id'=>'13579:CARD:2','cid'=>2222,'name'=>'Dos','fields'=>[],
    'slots'=>[['card_id'=>2,'section'=>'extra','quantity'=>1]],
    'sources'=>[],'synergy'=>['labels'=>[]]],
   ['id'=>'13579:CARD:3','cid'=>3333,'name'=>'Tres','fields'=>[],
    'slots'=>[['card_id'=>3,'section'=>'side','quantity'=>1]],
    'sources'=>[],'synergy'=>['labels'=>[]]],
 ],
 'routes'=>[],'skills'=>[],'engine_maps'=>[],'decisions'=>[],'practice'=>[]
];
$inventory=[
 'deck'=>['id'=>13579,'name'=>'Nexo Umbral'],
 'cards'=>[
  ['id'=>1,'section'=>'main','quantity'=>1],
  ['id'=>2,'section'=>'extra','quantity'=>1],
  ['id'=>3,'section'=>'side','quantity'=>1]
 ]
];
ygo_data_validate_a01_profile($record);
ygo_data_validate($record,$inventory);
$encoded=ygo_data_json($record);
$again=json_decode($encoded,true,128,JSON_THROW_ON_ERROR);
a01_assert($again['profile']===$profile,'JSON roundtrip lost six profile fields');
a01_assert(($again['a01_profile_contract']??null)==='station-x-a01-profile/1','Contract marker lost');
ygo_data_validate($again,$inventory);
$bad=$record; unset($bad['profile']['Resumen para ficha']); a01_reject($bad,'missing field');
$bad=$record; $bad['profile']['Estilo breve']=str_repeat('x',191);a01_reject($bad,'190 character bound');
$bad=$record; $bad['profile']['Resumen para ficha']=str_repeat('x',251);a01_reject($bad,'250 character bound');
$bad=$record; $bad['profile']['Tipo de estrategia']='Consultar el plan de juego';a01_reject($bad,'placeholder');
$bad=$record; $bad['profile']['Arquetipo']='Nexo Umbral — estudio individual';a01_reject($bad,'dirty archetype');
$bad=$record; $bad['profile']['Cómo se juega']='Poco';a01_reject($bad,'gameplan');
$bad=$record; $bad['profile']['Qué vigilar']='N/A';a01_reject($bad,'risks');
$bad=$record; $bad['a01_profile_contract']='unrecognized';a01_reject($bad,'unknown contract');
$legacy=$record;unset($legacy['a01_profile_contract'],$legacy['profile']['Estilo breve']);
ygo_data_validate_a01_profile($legacy); // Historical data remains readable, NOT certified T4/T5.
a01_assert(!isset($legacy['a01_profile_contract']),'Legacy data must not receive automatic A01 certification');
echo json_encode(['ok'=>true,'contract'=>'station-x-a01-profile/1','fields'=>6,
 'synthetic'=>true,'decks_analyzed'=>0,'legacy_compatible'=>true],JSON_UNESCAPED_UNICODE).PHP_EOL;
