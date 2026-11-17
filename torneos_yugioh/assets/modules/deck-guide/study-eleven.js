(function(root){
  'use strict';
  function mount(panel,data,ui){
    panel._studyElevenAbort?.abort();
    const controller=new AbortController();panel._studyElevenAbort=controller;
    const {esc,inline,photo,prose,page,go}=ui,photos=[...data.cards,...data.skills],routes=data.routes,hasChains=data.sections.some(s=>s.number===11);
    const text=n=>data.sections[n-1].source_text;
    const chunks=text=>text.split(/(?=^#### )/m).filter(s=>s.trim());
    const source=(body,label='Documentación completa recibida')=>`<details><summary>${label}</summary>${prose(body,photos)}</details>`;
    const picker=key=>`<label>Ruta del deck<select class="form-select" data-route-picker="${key}">${routes.map((r,i)=>`<option value="${i}">${esc(r.name)} · ${esc(r.category)}</option>`).join('')}</select></label>`;
    panel.querySelector('.checked-provenance').insertAdjacentHTML('beforebegin',
      page(8,`<p>Starters, extensores e interacciones del inventario. Una interacción aislada no equivale a un combo completo. Los checks y los pendientes se conservan por registro.</p>${chunks(text(8)).map((c,i)=>source(c,i?'Abrir '+c.split('\n')[0].replace(/^#+\s*/,''):'Clasificación y condiciones')).join('')}`)+
      page(9,`<p>${routes.length} fichas canónicas recibidas. Aperturas, continuaciones y transformaciones conservan su categoría. Las guías son documentales y su verificación integral sigue pendiente.</p>${picker('file')}<article data-route-file></article>${source(text(9),'Expediente completo: fichas, candidatos y límites de cobertura')}`)+
      page(10,`<p>Recorre las acciones con fotografías de las cartas de este deck. Cada estado corresponde al paso documentado; la distribución es ilustrativa cuando no se especifica la zona física exacta.</p>${picker('visual')}<article data-route-visual></article>${source(text(10),'Alcance de la guía y notas históricas de su desarrollo')}`)+
      (hasChains?page(11,`<p>Costes, ventanas, activación y resolución documentados. Una comprobación de la secuencia sin respuesta rival no acredita todas las cadenas posibles.</p>${picker('chains')}<article data-route-chains></article>${chunks(text(11)).map((c,i)=>source(c,i?'Abrir '+c.split('\n')[0].replace(/^#+\s*/,''):'Cobertura, candidatos y pendientes')).join('')}`):''));
    let routeIndex=0,stepIndex=0,mode='steps';
    const img=path=>{const p=photos.find(c=>c.image===path);return p?photo(p):'<p>Imagen pendiente de identificar.</p>';};
    const gallery=paths=>`<div class="study-map-photos">${paths.map(img).join('')}</div>`;
    const pieces=items=>gallery(items.filter(c=>c.photo).map(c=>c.photo))+items.filter(c=>!c.photo).map(c=>`<p>${esc(c.name)} · ${esc(c.status)}</p>`).join('');
    const badge=r=>`<p class="checked-notice">${esc(r.category)} · ${esc(r.status)}</p>`;
    function render(){
      const r=routes[routeIndex];
      panel.querySelectorAll('[data-route-picker]').forEach(s=>s.value=String(routeIndex));
      panel.querySelector('[data-route-file]').innerHTML=`<h4>${esc(r.name)}</h4>${badge(r)}<h4>Necesitas al comenzar</h4><p>${inline(r.hand)}</p>${gallery(r.hand_photos)}<h4>Resultado documentado</h4><p>${inline(r.summary)}</p><button type="button" data-route-open="10">Aprender paso a paso</button>${source(r.source_full,'Ficha íntegra: requisitos, costes, pasos, recursos y fuentes')}`;
      if(hasChains){
        const related=chunks(text(11)).filter(c=>c.includes(r.id)||c.includes(r.id.split(':A09:')[1]));
        panel.querySelector('[data-route-chains]').innerHTML=`<h4>${esc(r.name)}</h4>${badge(r)}${related.length?related.map(c=>prose(c,photos)).join(''):'<p>No hay una comprobación de cadenas individual vinculada inequívocamente a esta ruta. Consulta la cobertura general y sus pendientes debajo.</p>'}<button type="button" data-route-open="10">Consultar el paso y sus costes</button>`;
      }
      renderVisual();
    }
    function board(step){
      const b=step.zone_board,trace=step.zone_trace||{};
      return `<h4>Campo después del paso ${step.n}</h4><p>Posiciones físicas exactas: solo las que indique el texto. Una zona sin dato no significa que esté vacía.</p>${b?`<div class="study-duel-board"><section><h5>Monstruos</h5>${b.monsters?.length?pieces(b.monsters):'<p>Consultar estado textual</p>'}</section><section><h5>Magias, Trampas y Campo</h5>${b.spells?.length?pieces(b.spells):'<p>Consultar estado textual</p>'}</section></div>`:''}<dl class="study-state">${Object.entries(trace).map(([key,value])=>`<div><dt>${esc(({mano:'Mano',monstruos:'Monstruos',magias_trampas:'Magias / Trampas / Campo',cementerio:'Cementerio',destierro_propio:'Destierro propio',estado_rival:'Información rival',materiales:'Materiales',normal_extra:'Normal / Extra'})[key]||key.replaceAll('_',' '))}</dt><dd>${inline(value)}</dd></div>`).join('')}</dl>${!Object.keys(trace).length?`<p>${inline(step.state||'No existe una traza de zonas completa para este paso.')}</p>`:''}`;
    }
    function renderVisual(){
      const r=routes[routeIndex],s=r.steps[stepIndex];
      const modeButtons=[['map','Mapa de acciones'],['steps','Paso a paso'],['board','Campo final']].map(([key,label])=>`<button type="button" data-route-mode="${key}" aria-pressed="${mode===key}">${label}</button>`).join('');
      let body='';
      if(mode==='map')body=`<ol class="study-action-map">${r.steps.map((s,i)=>`<li><button type="button" data-route-step="${i}">Paso ${s.n} · ${inline(s.action||s.micro)}</button>${gallery(s.photos)}<p>${inline(s.movements.join(' · '))}</p>${i<r.steps.length-1?'<span class="study-action-arrow" aria-label="Continúa con el siguiente paso">↓</span>':''}</li>`).join('')}</ol><p>La secuencia muestra las acciones registradas; no se añaden ramas de apartados posteriores.</p>`;
      if(mode==='board')body=`${board(r.steps.at(-1))}<h4>Resultado y recursos</h4><p>${inline(r.summary)}</p>`;
      if(mode==='steps')body=`<div class="study-step-controls"><button type="button" data-route-prev ${stepIndex===0?'disabled':''}>Anterior</button><strong aria-live="polite">Paso ${stepIndex+1} de ${r.steps.length}</strong><button type="button" data-route-next ${stepIndex===r.steps.length-1?'disabled':''}>Siguiente</button></div><label>Ir al paso<select class="form-select" data-route-step-select>${r.steps.map((s,i)=>`<option value="${i}" ${i===stepIndex?'selected':''}>Paso ${s.n} · ${esc(s.action||s.micro)}</option>`).join('')}</select></label><h4>${inline(s.action||s.micro)}</h4>${gallery(s.photos)}<p>${inline(s.movements.join(' · '))}</p><p>${inline(s.declared_state)}</p>${source(s.text,'Acción completa, coste, ventana y resolución')}${board(s)}${hasChains?'<button type="button" data-route-open="11">Consultar cadenas y comprobaciones</button>':''}`;
      panel.querySelector('[data-route-visual]').innerHTML=`<h4>${esc(r.name)}</h4>${badge(r)}<h4>Mano inicial</h4><p>${inline(r.hand)}</p>${gallery(r.hand_photos)}${(r.initial_items||[]).length?`<section><h4>Otros recursos iniciales</h4>${r.initial_items.map(x=>`<p>${esc(x.zone==='GY'?'Cementerio':x.zone)} ? ${inline(x.note)}</p>${img(x.photo)}`).join('')}</section>`:''}<div class="study-step-controls" aria-label="Vistas de la guía">${modeButtons}</div>${body}${r.local_responses?.length?source(r.local_responses.join('\n'),'Advertencias y respuestas de esta misma ficha'):''}`;
    }
    panel.querySelectorAll('[data-route-picker]').forEach(s=>s.onchange=()=>{routeIndex=Number(s.value);stepIndex=0;render();});
    panel.addEventListener('change',e=>{if(e.target.matches('[data-route-step-select]')){stepIndex=Number(e.target.value);renderVisual();}},{signal:controller.signal});
    panel.addEventListener('click',e=>{
      const open=e.target.closest('[data-route-open]');if(open)go(Number(open.dataset.routeOpen));
      const view=e.target.closest('[data-route-mode]');if(view){mode=view.dataset.routeMode;renderVisual();}
      const step=e.target.closest('[data-route-step]');if(step){stepIndex=Number(step.dataset.routeStep);mode='steps';renderVisual();}
      if(e.target.closest('[data-route-prev]')&&stepIndex>0){stepIndex--;renderVisual();}
      if(e.target.closest('[data-route-next]')&&stepIndex<routes[routeIndex].steps.length-1){stepIndex++;renderVisual();}
    },{signal:controller.signal});
    render();
  }
  root.YgoStudyEleven={mount};
})(window);
