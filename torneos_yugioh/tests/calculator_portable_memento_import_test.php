<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/decks/data-package.php';

$source=dirname(__DIR__).'/assets/editorial/20261117-folder-delivery-v40/671.json';
$study=ygo_data_read($source);
$rules=$study['calculator']['rules']??[];
if(count($rules)!==316)throw new RuntimeException('Expected 316 portable Memento variants');
if(count(array_unique(array_column($rules,'id')))!==54)
    throw new RuntimeException('Expected 54 stable effect IDs');
$base=['deck_id'=>671,'format'=>$study['format'],'cards'=>$study['cards'],'routes'=>$study['routes']];
$raw="\x60\x60\x60json\n".ygo_data_json($study['calculator'])."\n\x60\x60\x60";
$accepted=ygo_data_calculator($raw,$base,671);
if(count($accepted['calculator']['rules']??[])!==316)
    throw new RuntimeException('Complete portable calculator payload was rejected');
if(count(array_filter($rules,static fn(array $r):bool=>($r['resolve'][0]['op']??'')==='xyzOverlay'))!==7)
    throw new RuntimeException('TY-PHON alternate material selectors missing');
$reject=static function(array $payload,string $label)use($base):void{
    $raw="\x60\x60\x60json\n".ygo_data_json($payload)."\n\x60\x60\x60";
    try{
        ygo_data_calculator($raw,$base,671);
        throw new RuntimeException('Invalid portable contract accepted: '.$label);
    }catch(InvalidArgumentException $expected){}
};
$duplicate=$study['calculator'];
$duplicate['rules'][]=$duplicate['rules'][0];
$reject($duplicate,'identical duplicate');
$foreign=$study['calculator'];
$foreign['rules'][0]['actor']['cid']=999999;
$reject($foreign,'foreign actor');
$bone=array_values(array_filter($rules,static fn(array $r):bool=>$r['id']==='671:bone-party:main'));
if(count($bone)!==43)throw new RuntimeException('Expected 43 verified Bone Party variants');
$inconsistent=$study['calculator'];
$inconsistent['rules']=[$bone[0],$bone[1]];
$inconsistent['rules'][1]['oncePerTurn']=!($inconsistent['rules'][0]['oncePerTurn']??false);
$reject($inconsistent,'different usage policy');
$typhon=array_values(array_filter($rules,static fn(array $r):bool=>($r['resolve'][0]['op']??'')==='xyzOverlay'));
$foreignMaterial=$study['calculator'];
$invalidOverlay=$typhon[0];
$invalidOverlay['actor']['cids'][0]=999999;
$foreignMaterial['rules']=[$invalidOverlay];
$reject($foreignMaterial,'foreign alternate TY-PHON material');
if(isset($base['calculator']))throw new RuntimeException('Import mutated input');
echo "PASS Memento PHP import: 316 variants, 54 IDs, 7 TY-PHON, duplicate/policy/foreign actor rejected.\n";
