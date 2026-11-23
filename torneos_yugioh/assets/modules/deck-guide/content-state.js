(function(root){
  'use strict';
  const strategy=root.YgoDeckStrategy;
  if(!strategy)return;
  let ticket=0;
  const registry='./assets/editorial/strategy-content-state.json';
  const validPath=p=>typeof p==='string'&&
    /^\.\/assets\/editorial\/[A-Za-z0-9_./-]+\.json$/.test(p)&&
    !p.slice(2).split('/').includes('..');
  const certified=(study,id)=>study&&study.schema==='station-x-curated-v62'&&
    Number(study.deck_id)===Number(id)&&
    study.a01_profile_contract==='station-x-a01-profile/1'&&
    study.player_guide?.schema==='station-x-player-guide/1'&&
    typeof study.folder_delivery?.imported_at==='string'&&
    study.folder_delivery.imported_at.length>0;
  // Historical V40 research was imported from the canonical GPT source before
  // native A01 certificates existed. It stays readable but never becomes A01-T5.
  const documentaryLegacy=(study,id)=>{
    const folder=study?.folder_delivery||{};
    return study?.schema==='station-x-curated-v62'&&Number(study.deck_id)===Number(id)&&
      typeof folder.source_file==='string'&&
      /^torneos_yugioh\/context\/gpt\/01_INFORMACION_PARA_IMPORTAR\/decks\/DECK_[A-Za-z0-9_]+\/DECK_[A-Za-z0-9_]+_CONTENIDO_CURADO\.md$/.test(folder.source_file)&&
      /^[a-f0-9]{64}$/i.test(String(folder.source_sha256||''));
  };
  const imported=(study,id)=>certified(study,id)||documentaryLegacy(study,id);
  function setProfile(study){
    const p=study?.profile||{},contract=root.YgoStudyPresentation;
    const style=study?(contract?.style(p)||p['Estilo de juego']||''):'';
    const brief=study?(contract?.brief(p)||p['Cómo se juega']||''):'';
    const archetype=study?(contract?.archetype(p)||p['Arquetipo']||''):'';
    for(const [selector,value] of [
      ['[data-profile-style]',style],['[data-profile-archetype]',archetype],
      ['[data-profile-gameplan]',brief]
    ]){const node=document.querySelector(selector);if(node)node.textContent=value||'Sin análisis importado';}
  }
  function label(value){
    const node=document.querySelector('[data-deck-category-open="strategy"] small');
    if(node)node.textContent=value;
  }
  function empty(host){
    host.querySelector('[data-checked-study]')?._curatedDispose?.();
    let message=host.querySelector('[data-strategy-empty]');
    if(!message){message=document.createElement('section');message.dataset.strategyEmpty='';
      message.className='panel';host.prepend(message);}
    message.innerHTML='<h3>Estilo y estrategia</h3><p>Sin análisis importado desde GPT para este deck.</p>';
    for(const child of host.children){
      child.hidden=child!==message;child.style.display=child.hidden?'none':'';
    }
    setProfile(null);label('Sin análisis importado');host.style.visibility='';
  }
  strategy.render=function(deck){
    const current=++ticket,host=document.querySelector('.deck-strategy-profile');
    if(!host)return;
    host.style.visibility='hidden';
    fetch(registry,{cache:'no-store',credentials:'same-origin'}).then(r=>{
      if(!r.ok)throw Error('Registro editorial no disponible');
      return r.json();
    }).then(async state=>{
      if(current!==ticket)return;
      const entry=state.checked_studies?.[String(deck.id)];
      if(!entry||entry.presentation!=='curated-v62'||!validPath(entry.path))return empty(host);
      const res=await fetch(entry.path,{cache:'no-store',credentials:'same-origin'});
      if(!res.ok)throw Error('Paquete GPT no disponible');
      const study=await res.json();
      if(current!==ticket)return;
      if(!imported(study,deck.id)||study.cards?.length!==entry.cards)return empty(host);
      setProfile(study);
      host.querySelector('[data-strategy-empty]')?.remove();
      await root.YgoCheckedStudy.render(host,deck,entry,()=>current===ticket);
      if(current!==ticket)return;
      label(certified(study,deck.id)?'Guía importada A01':'Guía documental GPT anterior');host.style.visibility='';
    }).catch(()=>{if(current===ticket)empty(host);});
  };
  root.YgoA01ImportedProfile={certified,imported,documentaryLegacy};
})(window);
