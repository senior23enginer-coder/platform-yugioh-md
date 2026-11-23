(function(root){
  'use strict';
  async function mount(host,deck,isCurrent=()=>true){
    host.innerHTML='<p role="status">Cargando guías visuales y combos documentados…</p>';
    try{
      const response=await fetch('./assets/editorial/strategy-content-state.json?v=20261113-combo-library-164',{cache:'no-store'});
      if(!response.ok)throw Error('No se pudo consultar la biblioteca de guías.');
      const state=await response.json(),entry=state.checked_studies?.[String(deck.id)];
      if(!isCurrent())return;
      if(!entry||entry.presentation!=='curated-v62'){
        host.innerHTML='<p>No hay guías visuales documentadas disponibles para este deck en la biblioteca actual.</p>';return;
      }
      const source=entry.path;
      if(typeof source!=='string'||!/^\\.\\/assets\\/editorial\\/[A-Za-z0-9_./-]+\\.json$/.test(source)||source.includes('..'))
        throw Error('Ruta de importación inválida');
      const documentResponse=await fetch(source,{cache:'no-store',credentials:'same-origin'});
      if(!documentResponse.ok)throw Error('No se pudo leer el paquete activo');
      const study=await documentResponse.json();
      if(!isCurrent())return;
      if(study.schema!=='station-x-curated-v62'||Number(study.deck_id)!==Number(deck.id)||
        study.a01_profile_contract!=='station-x-a01-profile/1'||
        study.player_guide?.schema!=='station-x-player-guide/1'||
        !study.folder_delivery?.imported_at||!Array.isArray(study.routes)||!study.routes.length){
        host.innerHTML='<p>Sin combos y estrategias importados desde GPT para este deck.</p>';
        return;
      }
      await root.YgoCheckedStudy.render(host,deck,{...entry,libraryOnly:true},isCurrent);
    }catch(error){
      if(!isCurrent())return;
      host.innerHTML='<p role="alert">No se pudieron cargar las guías documentadas.</p><button type="button" data-cc-editorial-retry>Reintentar carga de guías</button>';
      host.querySelector('[data-cc-editorial-retry]').onclick=()=>mount(host,deck,isCurrent);
    }
  }
  root.YgoComboEditorial={mount};
})(window);
