(function(root){
  'use strict';
  const literal=v=>String(v??'').trim();
  const auditPrefix=/^Dictamen(?:\s+editorial(?:\s+[A-Z_]+)?)?:\s*/i;
  const pending=/^(?:consultar|pendiente|sin (?:datos|información)|no determinado)\b/i;
  // Technical references stay in metadata; never display them to players.
  const auditLeak=/\bCID\s*\d+\b|\b[CV]\d{2}\b|\bA(?:0[1-9]|10[AB]?)\s*[-–]?\s*T?\d*\b|\beffect_id\b|\broute_id\b|PARCIAL_AVANZADO|dictamen\s+editorial|PENDIENTE_REPLAY|cat[aá]logo completo|consultar el plan|estudio individual|iteraci[oó]n\s*\d+|cobertura\s+documental|validaci[oó]n\s+observada|evaluaci[oó]n\s+editorial|no\s+(?:escribir|redactar|inferir|inventar)\b/iu;
  const publicText=value=>{const v=literal(value);return v&&!pending.test(v)&&!auditLeak.test(v)?v:'';};
  function firstSentence(raw,max=220){
    const cleaned=literal(raw).replace(auditPrefix,'').replace(/\s+/g,' ');
    const end=cleaned.match(/^.*?[.!?](?=\s|$)/u)?.[0]||cleaned;
    const excerpt=end.length>max?end.slice(0,max).replace(/\s+\S*$/u,'').trim()+'…':end;
    return excerpt.charAt(0).toLocaleUpperCase('es')+excerpt.slice(1);
  }
  function style(profile={},certified=false){
    if(certified){
      // A01 has already checked the literal field and its 190-character limit.
      const exact=publicText(profile['Estilo breve']);
      return exact&&Array.from(exact).length<=190?exact:'';
    }
    for(const key of ['Estilo breve','Tipo de estrategia']){
      const value=publicText(profile[key]);
      if(value){
        const short=firstSentence(value,190);
        if(short)return short;
      }
    }
    return '';
  }
  function archetype(profile={}){
    return literal(profile['Arquetipo']||'').replace(/\s+[—–-]\s+(?:estudio individual|profundización|iteración|auditoría)\b.*$/iu,'').trim();
  }
  function openingPhotos(route,cards){
    const opening=literal(route?.opening);
    const hand=opening.match(/\bmano\s*(?:inicial\s*)?(?:incluye|contiene|:)\s*([^.;]+)/iu);
    // A historical route may have included GY/Deck references in hand_photos.
    // Only label these as an initial hand when the source explicitly says "mano".
    if(hand){
      const verified=photosFromText(hand[1],cards,6);
      if(verified.length)return verified;
    }
    return Array.isArray(route?.hand_photos)?route.hand_photos:[];
  }
  function brief(profile={}){
    // A01 already validates the <=250-character summary. Keep all sentences;
    // taking only the first one silently loses player-facing instructions.
    return publicText(profile['Resumen para ficha']);
  }
  function gamePlan(profile={}){return publicText(profile['Cómo se juega']);}
  function risks(profile={}){return publicText(profile['Qué vigilar']);}
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
  const API={style,brief,gamePlan,risks,archetype,openingPhotos,photosFromText,stepPhotos,resultPhotos,status};
  root.YgoStudyPresentation=API;
  if(typeof module==='object'&&module.exports)module.exports=API;
})(typeof window!=='undefined'?window:globalThis);
