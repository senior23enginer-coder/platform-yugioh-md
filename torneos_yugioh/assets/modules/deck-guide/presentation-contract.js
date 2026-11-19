(function(root){
  'use strict';
  const literal=v=>String(v??'').trim();
  const auditPrefix=/^Dictamen(?:\s+editorial(?:\s+[A-Z_]+)?)?:\s*/i;
  const pending=/^(?:consultar|pendiente|sin (?:datos|información)|no determinado)\b/i;
  function firstSentence(raw,max=220){
    const cleaned=literal(raw).replace(auditPrefix,'').replace(/\s+/g,' ');
    const end=cleaned.match(/^.*?[.!?](?=\s|$)/u)?.[0]||cleaned;
    const excerpt=end.length>max?end.slice(0,max).replace(/\s+\S*$/u,'').trim()+'…':end;
    return excerpt.charAt(0).toLocaleUpperCase('es')+excerpt.slice(1);
  }
  function style(profile={}){
    for(const key of ['Estilo breve','Tipo de estrategia','Estilo de juego','Cómo se juega']){
      const value=literal(profile[key]);
      if(value&&!pending.test(value)){
        const short=firstSentence(value,190);
        if(short)return short;
      }
    }
    return '';
  }
  function brief(profile={}){
    const raw=profile['Resumen para ficha']||profile['Cómo se juega']||profile['Estilo de juego']||'';
    return firstSentence(raw,250);
  }
  function containsName(haystack,term){
    if(!term||term.length<4)return false;
    let at=haystack.indexOf(term);
    while(at>=0){
      const before=at>0?haystack[at-1]:'';
      const after=haystack[at+term.length]||'';
      if((!before||!(/[\p{L}\p{N}]/u).test(before))&&(!after||!(/[\p{L}\p{N}]/u).test(after)))return true;
      at=haystack.indexOf(term,at+1);
    }
    return false;
  }
  // Explicitly named cards only. Never infer effects, materials or board zones.
  function photosFromText(raw,cards=[],max=4){
    const narrative=literal(raw).normalize('NFD').replace(/\p{Diacritic}/gu,'').toLocaleLowerCase('es');
    if(!narrative)return [];
    const found=[],unique=new Set();
    for(const card of cards){
      if(!card||!literal(card.image))continue;
      const names=[card.name,...(Array.isArray(card.source_aliases)?card.source_aliases:[])];
      // Use a suffix of an official compound name only when it is unambiguous
      // among this deck's own cards (e.g. «Creación Combinada»).
      const suffix=literal(card.name).split(/\s+[-—–]\s+/u).at(-1);
      if(suffix&&suffix!==literal(card.name)&&suffix.length>=7
          &&cards.filter(c=>literal(c.name).endsWith(suffix)).length===1)names.push(suffix);
      const aliases=names.map(x=>literal(x).normalize('NFD').replace(/\p{Diacritic}/gu,'').toLocaleLowerCase('es'));
      if(!aliases.some(name=>containsName(narrative,name)))continue;
      const key=String(card.cid??card.id??card.name);
      if(unique.has(key))continue;
      unique.add(key);
      found.push({cid:card.cid,name:card.name,image:card.image,full_image:card.full_image||card.image,
        label:'Carta mencionada; verifica su acción y zona en la guía'});
      if(found.length>=max)break;
    }
    return found;
  }
  function stepPhotos(step,cards){
    if(Array.isArray(step?.photos)&&step.photos.length)return step.photos;
    return photosFromText([step?.action,step?.detail].filter(Boolean).join(' '),cards,4);
  }
  function resultPhotos(route,cards){
    if(Array.isArray(route?.board)&&route.board.length)return route.board;
    return photosFromText(route?.result||'',cards,6);
  }
  function status(route){
    if(route?.endboard?.variants?.length)return 'structured';
    if(Array.isArray(route?.board)&&route.board.length)return 'partial';
    return 'documented';
  }
  const API={style,brief,photosFromText,stepPhotos,resultPhotos,status};
  root.YgoStudyPresentation=API;
  if(typeof module==='object'&&module.exports)module.exports=API;
})(typeof window!=='undefined'?window:globalThis);
