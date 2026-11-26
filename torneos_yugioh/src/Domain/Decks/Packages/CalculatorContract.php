<?php
declare(strict_types=1);

namespace StationX\Yugioh\Domain\Decks\Packages;

use InvalidArgumentException;

final class CalculatorContract
{
    public static function extract(string $raw,array $data,int $deckId): array
    {
        preg_match_all('~```json\s*\R(.*?)\R```~s',$raw,$blocks,PREG_SET_ORDER);$found=false;
        foreach($blocks as $block){
            $value=json_decode($block[1],true);
            if(json_last_error()!==JSON_ERROR_NONE&&str_contains($block[1],'station-x-calculator/1'))throw new InvalidArgumentException('JSON de calculadora dañado; no se descartará silenciosamente.');
            if(!is_array($value)||($value['schema']??'')!=='station-x-calculator/1')continue;
            if($found||strlen($block[1])>2097152||(int)($value['deck_id']??0)!==$deckId||($value['format']??'')!==($data['format']??'')||!is_array($value['rules']??null)||count($value['rules'])>1000)throw new InvalidArgumentException('Contrato de calculadora duplicado, de otro deck o incompleto.');
            $found=false;$seen=[];$cids=array_column($data['cards']??[],'cid');
            foreach($value['rules'] as $rule){
                $cid=$rule['actor']['cid']??null;
                $actorValid=is_int($cid)&&in_array($cid,$cids,true);
                if(!$actorValid&&$cid===null){
                    // Exact TY-PHON alternate Xyz procedure uses a list of possible
                    // materials instead of one CID for its actor.
                    $materials=$rule['actor']['cids']??null;
                    $result=$rule['resolve'][0]??null;
                    $actorValid=is_array($materials)&&count($materials)>0&&count($materials)<=60
                        &&count($materials)===count(array_unique($materials))
                        &&count(array_diff($materials,$cids))===0
                        &&($rule['actor']['side']??null)==='self'
                        &&($rule['actor']['zones']??null)===['monsters']
                        &&($rule['actor']['faceUp']??null)===true
                        &&is_array($result)&&count($rule['resolve'])===1
                        &&($result['op']??null)==='xyzOverlay'
                        &&($result['cid']??null)===19184
                        &&($result['recipe']??null)==='typhon-highest-atk'
                        &&in_array(19184,$cids,true)
                        &&str_contains((string)($rule['source']??''),'cid=19184');
                }
                if(!is_array($rule)||($rule['version']??0)!==1||($rule['deckId']??0)!==$deckId||($rule['format']??'')!==$value['format']||!is_string($rule['id']??null)||!$actorValid)throw new InvalidArgumentException('Regla de calculadora de otro deck o carta no identificada.');
                foreach(['guards','select','costs','resolve','tags','assumptions'] as $field)if(!is_array($rule[$field]??null))throw new InvalidArgumentException('Regla sin '.$field);
                $id=$rule['id'];
                $definition=json_encode($rule,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                $policy=[$cid,$rule['oncePerTurn']??null,$rule['oncePerName']??null,$rule['activation']??null,$rule['chainSpeed']??null];
                if(isset($seen[$id])){
                    if($seen[$id]['policy']!==$policy||in_array($definition,$seen[$id]['definitions'],true))
                        throw new InvalidArgumentException('ID de regla duplicado o variantes incompatibles: '.$id);
                    $seen[$id]['definitions'][]=$definition;
                }else $seen[$id]=['policy'=>$policy,'definitions'=>[$definition]];
            }
            $found=true;$data['calculator']=$value;
        }
        return $data;
    }

    public static function withoutContract(string $raw): string
    {
        return preg_replace_callback('~```json\s*\R(.*?)\R```~s',static function(array $m):string{
            $v=json_decode($m[1],true);return is_array($v)&&($v['schema']??'')==='station-x-calculator/1'?'':$m[0];
        },$raw);
    }
}
