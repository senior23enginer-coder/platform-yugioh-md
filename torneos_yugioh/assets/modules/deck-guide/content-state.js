(function (root) {
  'use strict';
  const strategy=root.YgoDeckStrategy;
  if(!strategy)return;
  const original=strategy.render;
  let ticket=0,emptyState=false,studyState=false,studyLabel='Cartas documentadas';
  const policy=fetch('./assets/editorial/strategy-content-state.json?v=20261113-combo-library-164',{cache:'no-store'}).then(r=>{if(!r.ok)throw Error('Estado de contenido no disponible.');return r.json();});
  const hasContent=p=>p&&(['play_style','primary_game_plan','win_condition','resource_engine','opening_plan','mid_game_plan','late_game_plan','going_first_plan','going_second_plan','side_deck_strategy','analysis_notes'].some(k=>String(p[k]||'').trim())||['strengths_json','weaknesses_json','strategy_tags_json'].some(k=>{try{return JSON.parse(p[k]||'[]').length>0;}catch{return false;}}));
  function label(){
    const node=document.querySelector('[data-deck-category-open="strategy"] small');
    if(emptyState&&node&&node.textContent!=='Sin información')node.textContent='Sin información';
    if(studyState&&node&&node.textContent!==studyLabel)node.textContent=studyLabel;
  }
  strategy.render=function(deck,profile,observed){
    const current=++ticket,host=document.querySelector('.deck-strategy-profile');if(!host)return;
    // Never paint stale source documents while the reset state is being resolved.
    host.style.visibility='hidden';
    policy.then(async state=>{
      if(current!==ticket)return;
      studyState=false;
      const checked=state.checked_studies?.[String(deck.id)];
      if(checked){
        // The public deck overview and the guide use the SAME verified study.
        // A stale response must never update another deck's profile.
        const source=checked.path;
        if(typeof source==='string'&&/^\.\/assets\/editorial\/[a-zA-Z0-9_./-]+\.json$/.test(source)
          &&!source.slice(2).split('/').includes('..')){
          fetch(source,{cache:'no-store',credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(study=>{
            if(current!==ticket||!study||study.schema!=='station-x-curated-v62'||Number(study.deck_id)!==Number(deck.id))return;
            const contract=root.YgoStudyPresentation;
            const style=contract?.style(study.profile);
            const brief=contract?.brief(study.profile);
            const styleElement=document.querySelector('[data-profile-style]');
            const planElement=document.querySelector('[data-profile-gameplan]');
            if(style&&styleElement)styleElement.textContent=style;
            if(planElement){planElement.textContent=brief||'Consulta la guía de juego para conocer los requisitos y recursos.';}
          }).catch(()=>{});
        }
        emptyState=false;
        await root.YgoCheckedStudy.render(host,deck,checked,()=>current===ticket);
        if(current!==ticket)return;
        studyLabel=checked.presentation==='curated-v62'?'Guía de juego':checked.eligibility_policy==='checked-only-v1'?'Contenido con check individual':checked.sections?`Apartados 01–${String(checked.sections).padStart(2,'0')}`:'Cartas documentadas';studyState=true;host.style.visibility='';label();return;
      }
      if(!(state.cleared_deck_ids||[]).includes(Number(deck.id))){emptyState=false;host.style.visibility='';return original.call(strategy,deck,profile,observed);}
      const newManualProfile=Number(profile?.revision||0)>Number(state.cleared_profile_revisions?.[String(deck.id)]||0)&&hasContent(profile);
      emptyState=!newManualProfile;
      // The native editor remains available. Old editorial deliveries stay inactive.
      let message=host.querySelector('[data-strategy-empty]');
      if(!message){message=document.createElement('section');message.dataset.strategyEmpty='';message.className='panel';message.innerHTML='<h3>Estilo y estrategia</h3><p>Sin información cargada en este apartado.</p>';host.prepend(message);}
      for(const child of host.children){child.hidden=child===message?newManualProfile:child.classList.contains('deck-strategy-editor')?false:!newManualProfile;child.style.display=child.hidden?'none':'';}
      if(emptyState){
        const editor=host.querySelector('.deck-strategy-editor');
        if(editor){editor.open=false;for(const input of editor.querySelectorAll('input:not([type="hidden"]),textarea'))input.value=input.type==='number'?'0':'';}
        const status=host.querySelector('[data-deck-analysis-editor-state]');if(status)status.textContent='Perfil vacío. Puedes cargar información nueva.';
      }
      host.style.visibility='';label();
    }).catch(()=>{
      if(current!==ticket)return;
      emptyState=true;studyState=false;
      for(const child of host.children)child.hidden=true;
      let error=host.querySelector('[data-strategy-state-error]');if(!error){error=document.createElement('p');error.dataset.strategyStateError='';host.append(error);}
      error.hidden=false;error.textContent='No se pudo consultar el estado del apartado. Recarga la página.';host.style.visibility='';label();
    });
  };
  const summary=document.querySelector('[data-deck-category-open="strategy"] small');
  if(summary)new MutationObserver(label).observe(summary,{childList:true,subtree:true});
})(window);
