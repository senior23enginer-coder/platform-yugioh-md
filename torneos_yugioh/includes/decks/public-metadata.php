<?php
declare(strict_types=1);

/** Read-only projection: native edits, active study metadata and saved cards. */
function ygo_deck_list_metadata(array $decks,array $cards,array $profiles,?string $app=null): array
{
    $app??=dirname(__DIR__,2);
    $policy=json_decode((string)@file_get_contents($app.'/assets/editorial/strategy-content-state.json'),true)??[];
    $root=realpath($app.'/assets/editorial');$counts=[];$manual=[];
    $clean=static function(mixed $value): string {
        $value=trim((string)$value);
        return preg_match('/^(?:editable|pendiente(?: de documentar)?|sin (?:datos|estilo)|n\/a|null|tcg|tcg advanced|ocg|goat|rush[ _-]?duel)$/iu',$value)?'':$value;
    };
    foreach($cards as $card){
        if(trim((string)($card['card_name']??''))!==''&&(int)($card['quantity']??0)>0)
            $counts[(int)$card['deck_id']]=($counts[(int)$card['deck_id']]??0)+(int)$card['quantity'];
    }
    foreach($profiles as $profile){
        $id=(int)$profile['deck_id'];
        if(in_array($id,$policy['cleared_deck_ids']??[],true)&&(int)($profile['revision']??0)<=(int)($policy['cleared_profile_revisions'][(string)$id]??0))continue;
        if(!isset($manual[$id]))$manual[$id]=$clean($profile['play_style']??'');
    }
    foreach($decks as &$deck){
        $id=(int)$deck['id'];$entry=$policy['checked_studies'][(string)$id]??null;$study=[];
        if($root&&is_string($entry['path']??null)){
            $file=realpath($app.'/'.ltrim($entry['path'],'./'));
            if($file&&str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$root).'/')){
                $doc=json_decode((string)file_get_contents($file),true);
                if((int)($doc['deck_id']??0)===$id&&is_array($doc['profile']??null))$study=$doc['profile'];
            }
        }
        $deck['display_style']=$manual[$id]??'';
        if($deck['display_style']==='')$deck['display_style']=$clean($deck['style']??'');
        if($deck['display_style']==='')$deck['display_style']=$clean($study['Tipo de estrategia']??'')?:$clean($study['Estilo de juego']??'');
        $archetype=$clean($deck['archetype']??'');
        if($archetype===''||mb_strtolower($archetype)===mb_strtolower((string)$deck['name']))$archetype=$clean($study['Arquetipo']??$archetype);
        $deck['display_archetype']=$archetype?:$deck['name'];
        $deck['card_count']=$counts[$id]??0;
        $deck['has_cards']=$deck['card_count']>0;
    }
    unset($deck);
    return $decks;
}
