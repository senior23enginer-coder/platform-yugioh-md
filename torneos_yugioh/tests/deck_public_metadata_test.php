<?php
declare(strict_types=1);
require __DIR__.'/../includes/decks/public-metadata.php';
$fixture=json_decode(file_get_contents(__DIR__.'/fixtures/calculator_inventory.json'),true,128,JSON_THROW_ON_ERROR);
$decks=$fixture['allDecks'];$cards=$fixture['cards'];$before=json_encode([$decks,$cards]);
$decks[]=['id'=>99999,'name'=>'Vacío','style'=>'Editable','archetype'=>'Vacío'];
$cards[]=['deck_id'=>99999,'card_name'=>'Fila sin copias','quantity'=>0];
$rows=ygo_deck_list_metadata($decks,$cards,[]);$byId=array_column($rows,null,'id');
foreach($decks as &$d){if(in_array($d['id'],[704,705,706,707],true))$d['style']='Rush Duel';}unset($d);
$formatLabels=ygo_deck_list_metadata($decks,$cards,[]);
if(!str_contains(array_column($formatLabels,null,'id')[704]['display_style'],'Fusión'))throw new RuntimeException('Se confundió el formato Rush con el estilo de juego.');
foreach($fixture['allDecks'] as $d){if($byId[$d['id']]['display_style']==='')throw new RuntimeException('No se cargó el estilo del deck '.$d['id']);}
if(!str_contains($byId[665]['display_style'],'control')||$byId[665]['display_archetype']!=='Kashtira.')throw new RuntimeException('No se usó el perfil documentado de Kashtira.');
if(!$byId[665]['has_cards']||$byId[99999]['has_cards']||$byId[99999]['display_style']!=='')throw new RuntimeException('Indicador de cartas o placeholder incorrecto.');
$manual=ygo_deck_list_metadata($fixture['allDecks'],$fixture['cards'],[['deck_id'=>665,'play_style'=>'Mi estilo actualizado','revision'=>999999]]);
if(array_column($manual,null,'id')[665]['display_style']!=='Mi estilo actualizado')throw new RuntimeException('Se perdió una edición nueva del perfil.');
if(json_encode([$fixture['allDecks'],$fixture['cards']])!==$before)throw new RuntimeException('El inventario fue modificado.');
echo json_encode(['ok'=>true,'documentedStyles'=>11,'nativeEditsPreserved'=>true,'cardMarkerFromInventory'=>true,'zeroQuantityExcluded'=>true,'inventoryUnchanged'=>true]),PHP_EOL;
