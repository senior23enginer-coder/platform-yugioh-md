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
      await root.YgoCheckedStudy.render(host,deck,{...entry,libraryOnly:true},isCurrent);
    }catch(error){
      if(!isCurrent())return;
      host.innerHTML='<p role="alert">No se pudieron cargar las guías documentadas.</p><button type="button" data-cc-editorial-retry>Reintentar carga de guías</button>';
      host.querySelector('[data-cc-editorial-retry]').onclick=()=>mount(host,deck,isCurrent);
    }
  }
  root.YgoComboEditorial={mount};
})(window);
