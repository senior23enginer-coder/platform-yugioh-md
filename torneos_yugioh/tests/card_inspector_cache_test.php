<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/deck_builder.php';
$dir=sys_get_temp_dir().'/ygo-inspector-'.bin2hex(random_bytes(8));mkdir($dir);
$check=static function(bool $pass,string $label):void{if(!$pass)throw new RuntimeException($label);};
try{
    $check(ygo_card_inspector_cached(100,$dir)===null,'Missing cache must not invent text or fetch a provider');
    $text='Once per turn: detach 1 material; apply the following effect.';
    file_put_contents($dir.'/card-100.json',json_encode(['id'=>100,'description'=>$text,'image'=>'small.jpg']));
    $card=ygo_card_inspector_cached(100,$dir);
    $check($card['description']===$text,'Printed effect must remain exact');
    $check($card['image']===null,'Never label a thumbnail as the full image');
    file_put_contents($dir.'/image-full-100.jpg','test');
    $card=ygo_card_inspector_cached(100,$dir);
    $check(str_contains($card['image'],'size=full&local=1'),'Local inspector only links existing full image');
    file_put_contents($dir.'/card-100.json',json_encode(['id'=>200,'description'=>'Wrong card']));
    $rejected=false;try{ygo_card_inspector_cached(100,$dir);}catch(RuntimeException){$rejected=true;}
    $check($rejected,'Reject wrong provider identity');
    echo "Card inspector cached text: PASS; exact effect, full-image availability, identity and local-only reads.\n";
}finally{foreach(['card-100.json','image-full-100.jpg'] as $name)if(is_file($dir.'/'.$name))unlink($dir.'/'.$name);rmdir($dir);}
