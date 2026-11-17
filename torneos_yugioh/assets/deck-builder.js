(function(root){
  'use strict';
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const labels={main:'Main Deck',extra:'Extra Deck',side:'Side Deck',reserve:'Reserva de cartas'},instances=new WeakMap();
  const image=id=>id?`./?api=deck_builder&op=image&id=${Number(id)}`:'';
  const rowsFrom=rows=>(rows||[]).map(r=>({existing_id:Number(r.id),card_id:Number(r.ygoprodeck_id)||0,name:r.card_name,quantity:Number(r.quantity)||1,section:String(r.section||'main').toLowerCase()}));
  async function request(url,options={}){const response=await fetch(url,{credentials:'same-origin',cache:'no-store',...options});let data;try{data=await response.json();}catch(_){throw new Error('No se pudo leer la respuesta. Inténtalo de nuevo.');}if(!response.ok||!data.ok)throw new Error(data.message||'No se pudo completar la operación.');return data;}
  function mount(node,deck,data){
    if(!node)return;
    let state=instances.get(node);
    if(state&&state.id===Number(deck.id)){state.data=data;return;}
    if(state?.dirty&&!confirm('Hay cambios sin guardar en la lista. ¿Descartarlos y cambiar de deck?'))return;
    state={id:Number(deck.id),deck,data,rows:rowsFrom((data.cards||[]).filter(c=>Number(c.deck_id)===Number(deck.id))),editing:false,dirty:false,busy:false,page:0,cards:[],hasMore:false,controller:null,epoch:0};instances.set(node,state);
    node.classList.remove('card-gallery');node.classList.add('deck-builder');
    const url=()=>`./?api=deck_builder&deck_id=${state.id}&season_context=${Number(data.currentSeasonId)||0}`;
    function status(message,error=false){const n=node.querySelector('[data-builder-message]');n.textContent=message;n.classList.toggle('is-error',error);n.setAttribute('role',error?'alert':'status');}
    function mark(){state.dirty=true;status('Cambios sin guardar.');}
    function thumbnail(id,name){return id?`<button type="button" class="card-inspect-trigger" data-card-inspect="${Number(id)}" data-card-name="${esc(name)}" aria-label="Ampliar ${esc(name)}"><img src="${image(id)}" alt="${esc(name)}" loading="lazy" width="48" height="70" draggable="false"></button>`:'';}
    function drawRows(){
      const lists=node.querySelector('[data-builder-lists]');lists.classList.toggle('is-editing',state.editing);
      lists.innerHTML=Object.entries(labels).map(([section,label])=>{const rows=state.rows.map((r,i)=>({...r,index:i})).filter(r=>r.section===section),count=rows.reduce((n,r)=>n+r.quantity,0);return `<section class="deck-builder-section" data-builder-section="${section}"><h4>${label}<span data-builder-count="${section}">${count} ${count===1?'carta':'cartas'}</span></h4>${section==='reserve'?'<p class="note">Inventario de opciones; no constituye el Side Deck definitivo.</p>':''}${rows.length?`<div class="deck-builder-cards">${rows.map(r=>state.editing?`<div class="deck-builder-row" draggable="true" data-builder-drag="${r.index}"><span class="deck-builder-drag-handle" aria-hidden="true">⠿</span>${thumbnail(r.card_id,r.name)}<span class="deck-builder-name">${esc(r.name)}</span><div class="deck-builder-quantity"><button type="button" class="btn btn-outline-info" data-builder-minus="${r.index}" aria-label="Quitar una copia de ${esc(r.name)}" ${r.quantity<=1?'disabled':''}>−</button><input class="form-control" type="number" min="1" max="99" step="1" value="${r.quantity}" data-builder-quantity="${r.index}" aria-label="Cantidad de ${esc(r.name)}"><button type="button" class="btn btn-outline-info" data-builder-plus="${r.index}" aria-label="Añadir una copia de ${esc(r.name)}" ${r.quantity>=99?'disabled':''}>+</button></div><label class="deck-builder-move-label">Mover a<select class="form-select" data-builder-move="${r.index}" aria-label="Mover ${esc(r.name)}">${Object.entries(labels).map(([key,text])=>`<option value="${key}" ${key===section?'selected':''}>${text}</option>`).join('')}</select></label><button type="button" class="btn btn-outline-danger" data-builder-remove="${r.index}" aria-label="Quitar ${esc(r.name)}">Quitar</button></div>`:`<div class="deck-builder-view-card">${thumbnail(r.card_id,r.name)}<span>${esc(r.name)}</span><b>×${r.quantity}</b></div>`).join('')}</div>`:'<p class="note">Todavía no hay cartas en esta sección.</p>'}</section>`;}).join('');
      function move(index,section){
        if(!state.editing||state.busy||!Object.hasOwn(labels,section))return;
        const row=state.rows[index];if(!row||row.section===section)return;
        // Keep individual records and their notes: save handles duplicate card stacks.
        const duplicate=state.rows.find(r=>r!==row&&r.card_id&&r.card_id===row.card_id&&r.section===section);
        if(duplicate){
          if(duplicate.quantity+row.quantity>99){status('El movimiento supera 99 copias en una fila.',true);drawRows();return;}
          duplicate.quantity+=row.quantity;
          duplicate.merged_ids=[...new Set([...(duplicate.merged_ids||[]),...(row.merged_ids||[]),row.existing_id].filter(Boolean))];
          state.rows.splice(index,1);
        }else row.section=section;mark();drawRows();status(`${row.name}: ${row.quantity} ${row.quantity===1?'copia movida':'copias movidas'} a ${labels[section]}. Cambios sin guardar.`);
      }
      lists.querySelectorAll('[data-builder-move]').forEach(select=>select.onchange=()=>move(Number(select.dataset.builderMove),select.value));
      lists.querySelectorAll('[data-builder-drag]').forEach(row=>{
        row.ondragstart=e=>{if(state.busy){e.preventDefault();return;}state.dragIndex=Number(row.dataset.builderDrag);e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('application/x-stationx-deck-card',String(state.dragIndex));row.classList.add('is-dragging');};
        row.ondragend=()=>{state.dragIndex=null;lists.querySelectorAll('.is-drop-target,.is-dragging').forEach(n=>n.classList.remove('is-drop-target','is-dragging'));};
      });
      lists.querySelectorAll('[data-builder-section]').forEach(section=>{
        section.ondragover=e=>{if(!state.editing||state.busy||state.dragIndex==null)return;e.preventDefault();e.dataTransfer.dropEffect='move';section.classList.add('is-drop-target');};
        section.ondragleave=e=>{if(!section.contains(e.relatedTarget))section.classList.remove('is-drop-target');};
        section.ondrop=e=>{e.preventDefault();if(state.dragIndex==null)return;const index=state.dragIndex;state.dragIndex=null;move(index,section.dataset.builderSection);};
      });
      lists.querySelectorAll('[data-builder-quantity]').forEach(input=>input.onchange=()=>{const n=Number(input.value),r=state.rows[Number(input.dataset.builderQuantity)];if(!Number.isInteger(n)||n<1||n>99){input.value=r.quantity;status('La cantidad debe ser un entero entre 1 y 99.',true);return;}r.quantity=n;mark();drawRows();});
      for(const [action,delta] of [['minus',-1],['plus',1]])lists.querySelectorAll(`[data-builder-${action}]`).forEach(b=>b.onclick=()=>{state.rows[Number(b.dataset[action==='minus'?'builderMinus':'builderPlus'])].quantity+=delta;mark();drawRows();});
      lists.querySelectorAll('[data-builder-remove]').forEach(b=>b.onclick=()=>{state.rows.splice(Number(b.dataset.builderRemove),1);mark();drawRows();});
      lists.querySelectorAll('img').forEach(img=>img.onerror=()=>{img.hidden=true;});
    }
    function render(){
      node.innerHTML=`<div class="deck-builder-heading"><div><h3>Lista de cartas</h3><p>Main Deck, Extra Deck, Side Deck y reservas independientes.</p></div><button type="button" class="btn btn-primary" data-builder-edit>Editar decklist</button></div><p class="deck-builder-message" data-builder-message role="status" aria-live="polite"></p><div data-builder-editor hidden><form class="deck-builder-search" data-builder-search><label>Buscar cartas<input class="form-control" type="search" name="q" required minlength="2" maxlength="100" placeholder="Nombre en inglés o código de carta" autocomplete="off"></label><label>Catálogo<select class="form-select" name="format"><option value="all">Todas las cartas</option><option value="tcg">TCG</option><option value="goat">GOAT</option><option value="rush duel">RUSH DUEL</option></select></label><button class="btn btn-outline-info" type="submit">Buscar</button></form><div class="deck-builder-search-meta"><label>Añadir a<select class="form-select" data-builder-target><option value="main">Main Deck</option><option value="extra">Extra Deck</option><option value="side">Side Deck</option><option value="reserve">Reserva de cartas</option></select></label><p>Busca por nombre en inglés o código. YGOPRODeck no admite búsquedas en español. Arrastra una carta entre secciones o usa «Mover a»; se mueve toda su cantidad. Guarda cuando termines.</p></div><p data-builder-search-status role="status" aria-live="polite">Busca una carta para comenzar.</p><div class="deck-builder-results" data-builder-results></div><div class="deck-builder-pagination"><button type="button" class="btn btn-outline-info" data-builder-prev disabled>Anterior</button><span data-builder-page></span><button type="button" class="btn btn-outline-info" data-builder-next disabled>Siguiente</button></div><small class="deck-builder-attribution">Catálogo e imágenes: <a href="https://ygoprodeck.com/api-guide/" target="_blank" rel="noopener">YGOPRODeck</a>.</small><div class="deck-builder-actions"><button type="button" class="btn btn-primary" data-builder-save>Guardar decklist</button><button type="button" class="btn btn-outline-info" data-builder-cancel>Cancelar edición</button><button type="button" class="btn btn-outline-info" data-builder-reload>Recargar lista</button></div></div><div data-builder-lists></div>`;
      const edit=node.querySelector('[data-builder-edit]'),editor=node.querySelector('[data-builder-editor]');
      edit.hidden=state.data.canEditEvents===false;
      async function load(){if(state.busy)return;state.busy=true;edit.disabled=true;status('Cargando lista guardada…');try{const response=await request(url());if(instances.get(node)!==state)return;if(!response.can_edit)throw new Error('No tienes permiso para editar este deck.');state.version=response.version;state.token=response.token;state.saved=response.cards;state.rows=rowsFrom(response.cards);state.dirty=false;state.editing=true;editor.hidden=false;edit.hidden=true;drawRows();status('Lista cargada. Añade cartas y guarda cuando termines.');}catch(e){status(e.message,true);}finally{state.busy=false;edit.disabled=false;}}
      edit.onclick=load;
      const form=node.querySelector('[data-builder-search]');const formats=deck.format_codes||[];form.elements.format.value=formats.includes('rush_duel')?'rush duel':formats.includes('goat')?'goat':formats.includes('tcg_advanced')?'tcg':'all';
      async function search(page){
        state.controller?.abort();const controller=new AbortController();state.controller=controller;const epoch=++state.epoch;
        const q=form.elements.q.value.trim(),format=form.elements.format.value,info=node.querySelector('[data-builder-search-status]');if(q.length<2){info.textContent='Escribe al menos dos caracteres.';return;}
        const results=node.querySelector('[data-builder-results]');results.innerHTML='';info.textContent='Buscando cartas…';node.querySelector('[data-builder-page]').textContent='';node.querySelector('[data-builder-prev]').disabled=true;node.querySelector('[data-builder-next]').disabled=true;
        try{const answer=await request(`./?api=deck_builder&op=search&q=${encodeURIComponent(q)}&format=${encodeURIComponent(format)}&page=${page}`,{signal:controller.signal});if(epoch!==state.epoch||instances.get(node)!==state)return;state.cards=answer.cards;state.page=page;state.hasMore=answer.has_more;
          info.textContent=answer.cards.length?`${answer.total_rows} cartas encontradas · ${answer.cards.length} en esta página.${answer.stale?' Se muestra el catálogo guardado; la conexión no está disponible.':''}`:'No se encontraron cartas. Prueba otro nombre, código o catálogo.';
          results.innerHTML=answer.cards.map((c,i)=>`<article class="deck-builder-result">${thumbnail(c.id,c.name)}<div><b>${esc(c.name)}</b><small>${esc(c.type)} · ${c.id}</small><details><summary>Ver efecto</summary><p>${esc(c.description)}</p></details><button type="button" class="btn btn-outline-info" data-builder-add="${i}">Añadir</button></div></article>`).join('');
          results.querySelectorAll('[data-builder-add]').forEach(b=>b.onclick=()=>{const c=state.cards[Number(b.dataset.builderAdd)],section=node.querySelector('[data-builder-target]').value,existing=state.rows.find(r=>r.card_id===c.id&&r.section===section);if(existing){if(existing.quantity>=99){status('La cantidad máxima por fila es 99.',true);return;}existing.quantity++;}else state.rows.push({existing_id:0,card_id:c.id,name:c.name,quantity:1,section});mark();drawRows();status(`${c.name} añadida a ${labels[section]}. Cambios sin guardar.`);});
          results.querySelectorAll('img').forEach(img=>img.onerror=()=>{img.hidden=true;});node.querySelector('[data-builder-prev]').disabled=page===0;node.querySelector('[data-builder-next]').disabled=!answer.has_more;node.querySelector('[data-builder-page]').textContent=(answer.total_pages?`Página ${page+1} de ${answer.total_pages}`:'Sin páginas');
        }catch(e){if(e.name!=='AbortError'&&epoch===state.epoch){info.textContent=e.message;node.querySelector('[data-builder-page]').textContent='';}}
      }
      form.onsubmit=e=>{e.preventDefault();search(0);};form.elements.format.onchange=()=>{if(form.elements.q.value.trim().length>=2)search(0);};node.querySelector('[data-builder-prev]').onclick=()=>search(Math.max(0,state.page-1));node.querySelector('[data-builder-next]').onclick=()=>search(state.page+1);
      node.querySelector('[data-builder-cancel]').onclick=()=>{if(state.busy)return;if(state.dirty&&!confirm('¿Descartar los cambios sin guardar?'))return;state.controller?.abort();state.epoch++;state.rows=rowsFrom(state.saved);state.dirty=false;state.editing=false;editor.hidden=true;edit.hidden=false;drawRows();status('Edición cancelada. La lista guardada se conserva.');};
      node.querySelector('[data-builder-reload]').onclick=()=>{if(!state.dirty||confirm('¿Descartar los cambios y cargar la lista guardada?'))load();};
      node.querySelector('[data-builder-save]').onclick=async()=>{
        if(state.busy)return;if(!state.rows.length&&!confirm('La lista quedará vacía. ¿Guardar?'))return;
        state.controller?.abort();state.epoch++;state.busy=true;const controls=[...node.querySelectorAll('button,input,select')];const disabled=controls.map(n=>n.disabled);controls.forEach(n=>n.disabled=true);status('Guardando lista…');
        try{const answer=await request(url(),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:state.token,version:state.version,cards:state.rows.map(r=>({existing_id:r.existing_id,merged_ids:r.merged_ids||[],card_id:r.card_id,quantity:r.quantity,section:r.section}))})});state.saved=answer.cards;state.version=answer.version;state.rows=rowsFrom(answer.cards);state.dirty=false;state.data.cards=[...(state.data.cards||[]).filter(c=>Number(c.deck_id)!==state.id),...answer.cards];drawRows();const cardList=document.querySelector('[data-profile-card-list]');if(cardList)cardList.innerHTML=answer.cards.map(c=>`<span><b>${c.quantity}× ${esc(c.card_name)}</b><small>${esc(labels[c.section]||c.section)}</small></span>`).join('')||'<p class="note">Sin cartas registradas.</p>';const metric=document.querySelector('[data-deck-category-metric="decklist"]');if(metric)metric.textContent=`${answer.cards.reduce((n,c)=>n+Number(c.quantity),0)} cartas`;status('Decklist guardada.');}
        catch(e){status(e.message+' Tus cambios siguen en el editor.',true);}finally{state.busy=false;controls.forEach((n,i)=>{if(n.isConnected)n.disabled=disabled[i];});}
      };
      drawRows();
    }
    render();
  }
  addEventListener('beforeunload',event=>{for(const node of document.querySelectorAll('[data-card-gallery]'))if(instances.get(node)?.dirty){event.preventDefault();event.returnValue='';break;}});
  root.YgoDeckBuilder={mount};
})(window);
