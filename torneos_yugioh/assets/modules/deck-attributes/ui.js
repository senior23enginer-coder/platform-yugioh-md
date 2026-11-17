(function(root){
 'use strict';
 const M=root.YgoDeckAttributeModel,esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let records=null,eligible=[],token='',canEdit=false,busy=false,dirty=false,lastDeck=null,world='',returnWorld='',draft={id:0,revision:0,attributes:[]},form,list,detail;
 const allDecks=()=>root.DML_DATA?.allDecks||[];
 const decks=()=>allDecks().filter(d=>M.isTcg(d)&&eligible.includes(Number(d.id)));
 const record=id=>(records||[]).find(r=>Number(r.deck_id)===Number(id))||{deck_id:Number(id),attributes:[],primary:null,revision:0,updated_at:null};
 const rows=()=>M.rows(decks(),records||[]);
 const badge=(c,main=false)=>`<span class="attribute-chip attr-${c}">${esc(M.catalog[c])}${main?'<small>Principal</small>':''}</span>`;
 const tags=a=>a.length?a.map((c,i)=>badge(c,i===0)).join(''):'<span class="note">Sin mundos asignados</span>';
 const date=s=>s?s.replace(/^(\d{4})-(\d{2})-(\d{2})/,'$3/$2/$1'):'—';
 function apiUrl(id){const q=new URLSearchParams({api:'deck_attributes'}),current=new URLSearchParams(location.search);if(id)q.set('deck_id',id);for(const k of ['public_season','public_live'])if(current.has(k))q.set(k,current.get(k));return '?'+q;}
 async function api(body,id){const response=await fetch(apiUrl(id),body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify(body),cache:'no-store'}:{cache:'no-store'});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.message||'No se pudieron consultar los mundos.');return data;}
 function status(text,error=false){for(const selector of ['[data-attribute-status]','[data-world-message]']){const n=document.querySelector(selector);if(n){n.textContent=text;n.classList.toggle('text-warning',error);}}}
 function message(text,error=false){const n=document.querySelector('[data-attribute-editor-message]');n.hidden=!text;n.textContent=text;n.classList.toggle('alert-warning',error);n.classList.toggle('alert-info',!error);}
 function renderDeck(deck){lastDeck=deck;const n=document.querySelector('[data-profile-attributes]'),native=allDecks().find(d=>Number(d.id)===Number(deck.id))||deck;if(!n)return;if(!M.isTcg(native)||records!==null&&!eligible.includes(Number(deck.id))){n.innerHTML='';return;}n.innerHTML=records===null?'<p class="note">Consultando mundos…</p>':`<h3>Mundos</h3><div class="attribute-tags">${tags(record(deck.id).attributes)}</div><button class="btn btn-outline-info btn-sm mt-2" type="button" data-attribute-edit="${Number(deck.id)}">Gestionar mundos</button>`;}
 function displays(){root.DML_DATA.deckAttributes=records;document.querySelectorAll('[data-attribute-tags-deck]').forEach(n=>{n.innerHTML=eligible.includes(Number(n.dataset.attributeTagsDeck))?tags(record(n.dataset.attributeTagsDeck).attributes):'';});if(lastDeck)renderDeck(lastDeck);}
 function renderList(){
  if(records===null)return;const roster=rows(),summary=M.worlds(roster);
  const q=list.querySelector('[data-world-search]').value,state=list.querySelector('[data-world-state]').value;
  const fold=v=>String(v).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  const found=summary.filter(w=>fold(w.name).includes(fold(q))&&(!state||(state==='assigned'?w.members.length>0:w.members.length===0)));
  const assigned=roster.filter(d=>d.attributeRecord.attributes.length).length;
  list.querySelector('[data-world-overview]').innerHTML=`<span><b>${summary.length}</b> mundos</span><span><b>${roster.length}</b> decks TCG</span><span><b>${assigned}</b> con mundos</span><span><b>${roster.length-assigned}</b> sin asignar</span>`;
  list.querySelector('[data-world-count]').textContent=`${found.length} de ${summary.length} mundos`;
  list.querySelector('[data-world-rows]').innerHTML=found.map(w=>`<tr><td data-label="Mundo"><div class="world-name"><span class="world-orb attr-${w.code}" aria-hidden="true"><i class="bi bi-globe2"></i></span><b>${esc(w.name)}</b></div></td><td data-label="Decks asociados"><b>${w.members.length}</b></td><td data-label="Como mundo principal">${w.primary}</td><td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-world-open="${w.code}"><i class="bi bi-eye" aria-hidden="true"></i> Ver mundo</button></td></tr>`).join('')||'<tr><td colspan="4">No hay mundos con estos filtros.</td></tr>';
 }
 function renderWorld(){
  if(!detail||records===null)return;
  if(!Object.hasOwn(M.catalog,world)){detail.querySelector('[data-world-title]').textContent='Selecciona un mundo';detail.querySelector('[data-world-members]').innerHTML='<tr><td colspan="4">Abre un mundo desde la tabla.</td></tr>';detail.querySelector('[data-world-add]').disabled=true;return;}
  detail.querySelector('[data-world-title]').textContent='Mundo '+M.catalog[world];
  detail.querySelector('[data-world-description]').textContent='Incluye sus integrantes como mundo principal o secundario. Cada deck debe pertenecer de uno a tres mundos. El primero es el principal.';
  const roster=rows(),members=roster.filter(d=>d.attributeRecord.attributes.includes(world)),search=detail.querySelector('[data-world-member-search]').value,mode=detail.querySelector('[data-world-membership]').value;
  const found=M.filter(members,{search}).filter(d=>!mode||(mode==='primary'?d.attributeRecord.primary===world:d.attributeRecord.primary!==world));
  detail.querySelector('[data-world-member-count]').textContent=`${found.length} de ${members.length} decks asociados`;
  detail.querySelector('[data-world-members]').innerHTML=found.map(d=>`<tr><td data-label="Deck"><b>${esc(d.name)}</b><small class="world-deck-meta">${d.attributeRecord.attributes.length} de 3 mundos</small></td><td data-label="Equipo">${esc(d.team||'Sin equipo')}</td><td data-label="Mundos"><div class="attribute-tags">${tags(d.attributeRecord.attributes)}</div></td><td data-label="Acciones"><div class="attribute-row-actions">${d.attributeRecord.primary!==world?`<button class="btn btn-outline-info btn-sm" type="button" data-world-primary="${d.id}" ${busy||!canEdit?'disabled':''}>Hacer principal</button>`:''}<button class="btn btn-outline-light btn-sm" type="button" data-attribute-edit="${d.id}" ${busy?'disabled':''}>Editar mundos</button>${canEdit?`<button class="btn btn-outline-danger btn-sm" type="button" data-world-remove="${d.id}" ${busy||d.attributeRecord.attributes.length<=1?'disabled':''} title="Conserva al menos un mundo; puedes cambiarlo desde Editar mundos">Quitar de este mundo</button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="4">No hay decks asociados que coincidan con estos filtros.</td></tr>';
  const select=detail.querySelector('[data-world-candidate]'),previous=select.value,q=detail.querySelector('[data-world-candidate-search]').value;
  const candidates=M.filter(roster,{search:q}).filter(d=>!d.attributeRecord.attributes.includes(world));
  select.innerHTML='<option value="">Selecciona un deck TCG</option>'+candidates.map(d=>`<option value="${d.id}" ${d.attributeRecord.attributes.length>=3?'disabled':''}>${esc(d.name)} · ${d.attributeRecord.attributes.length}/3 mundos${d.attributeRecord.attributes.length>=3?' · Límite alcanzado':''}</option>`).join('');
  select.value=previous;select.disabled=busy||!canEdit;candidateState();
 }
 function candidateState(){const select=detail.querySelector('[data-world-candidate]');detail.querySelector('[data-world-add]').disabled=busy||!canEdit||!select.value||!world;detail.querySelector('[data-world-candidate-note]').textContent=canEdit?'Los decks con tres mundos están deshabilitados. Quita uno desde «Editar mundos» antes de añadir otro.':'Esta vista es de consulta; la edición no está habilitada.';}
 function renderAll(){renderList();renderWorld();renderEditor();displays();document.querySelectorAll('[data-attribute-refresh]').forEach(b=>b.disabled=busy);}
 async function refresh(){if(busy)return false;try{status('Actualizando mundos…');const data=await api();records=data.records;eligible=data.eligible_deck_ids||[];token=data.token||'';canEdit=!!data.can_edit;renderAll();status(canEdit?'Solo decks TCG. Añadir o quitar integrantes guarda el cambio al momento.':'Consulta de mundos TCG.');return true;}catch(e){status(e.message,true);return false;}}
 function routeTo(route){const url=new URL(location.href);url.hash=route;root.history.pushState(null,'',url);root.dispatchEvent(new HashChangeEvent('hashchange'));}
 function setQuery(key,value){const url=new URL(location.href);if(value)url.searchParams.set(key,value);else url.searchParams.delete(key);root.history.replaceState(null,'',url);}
 function discard(){return !dirty||root.confirm('Tienes cambios sin guardar. ¿Quieres descartarlos?');}
 function openWorld(code){if(busy||!Object.hasOwn(M.catalog,code)||!discard())return;dirty=false;world=code;setQuery('world',code);setQuery('world_deck','');detail.querySelector('[data-world-member-search]').value='';detail.querySelector('[data-world-candidate-search]').value='';detail.querySelector('[data-world-membership]').value='';status('');renderWorld();routeTo('mundo_decks');}
 function history(rows){const n=document.querySelector('[data-attribute-history]');n.innerHTML=rows.length?`<ol>${rows.map(r=>`<li><b>Revisión ${r.revision}</b> · ${esc(date(r.updated_at))}<div class="attribute-tags">${tags(r.attributes)}</div></li>`).join('')}</ol>`:'Todavía no hay cambios registrados para este deck.';}
 async function selectDeck(id){if(id&&!decks().some(d=>Number(d.id)===Number(id))){message('Mundos solo está disponible para decks TCG.',true);return;}const r=record(id);draft={id:Number(id),revision:r.revision,attributes:[...r.attributes]};dirty=false;setQuery('world_deck',id||'');message('');history([]);renderEditor();if(id)try{const result=await api(null,id);if(draft.id===Number(id))history(result.history);}catch(e){message(e.message,true);}}
 async function openEditor(id=0){if(busy||!discard())return;returnWorld=location.hash==='#mundo_decks'?world:'';if(!returnWorld)setQuery('world','');await selectDeck(id);routeTo('mundos_deck_editar');}
 function renderEditor(){
  if(!form)return;const own=decks().find(d=>Number(d.id)===draft.id),assigned=!!record(draft.id).attributes.length;
  const select=form.querySelector('[data-attribute-deck]');select.innerHTML='<option value="">Selecciona un deck TCG</option>'+decks().map(d=>`<option value="${d.id}">${esc(d.name)}${d.team?' · '+esc(d.team):''}</option>`).join('');select.value=String(draft.id||'');select.disabled=busy||!canEdit;
  document.querySelector('[data-attribute-editor-title]').textContent=own?'Mundos de '+own.name:'Asignar mundos a un deck';
  form.querySelector('[data-attribute-editor-meta]').textContent=own?`${own.name} · ${own.team||'Sin equipo'}`:'Solo TCG; no es necesario tener cartas cargadas.';
  form.querySelector('[data-attribute-choices]').innerHTML=Object.keys(M.catalog).map(c=>`<button type="button" class="attribute-choice attr-${c}" data-attribute-add="${c}" ${busy||!canEdit||!own||draft.attributes.includes(c)||draft.attributes.length>=3?'disabled':''}>${esc(M.catalog[c])}${draft.attributes.includes(c)?' · Añadido':' +'}</button>`).join('');
  form.querySelector('[data-attribute-selection-count]').textContent=`${draft.attributes.length} de 3 mundos${draft.attributes.length?' · Principal: '+M.catalog[draft.attributes[0]]:''}`;
  form.querySelector('[data-attribute-selected]').innerHTML=draft.attributes.length?draft.attributes.map((c,i)=>`<li><div><b>${i+1}. ${esc(M.catalog[c])}</b><small>${i===0?'Mundo principal':'Mundo secundario'}</small></div><div class="attribute-row-actions">${i?`<button type="button" class="btn btn-outline-info btn-sm" data-attribute-promote="${c}" ${busy||!canEdit?'disabled':''}>Hacer principal</button>`:''}<button type="button" class="btn btn-outline-light btn-sm" data-attribute-drop="${c}" ${busy||!canEdit||draft.attributes.length<=1?'disabled':''} title="El deck debe conservar al menos un mundo">Quitar ${esc(M.catalog[c])}</button></div></li>`).join(''):'<li>Selecciona mundos de la lista superior.</li>';
  form.querySelector('[data-attribute-save]').disabled=busy||!canEdit||!own||!draft.attributes.length;
  document.querySelectorAll('[data-attribute-new]').forEach(b=>b.disabled=busy||!canEdit);
 }
 function accept(result){records=records.filter(r=>Number(r.deck_id)!==result.record.deck_id).concat(result.record);if(draft.id===result.record.deck_id){draft.revision=result.record.revision;draft.attributes=[...result.record.attributes];dirty=false;history(result.history);}renderAll();}
 async function save(event){event.preventDefault();if(busy||!canEdit)return;try{M.validate(draft.attributes);busy=true;renderAll();message('Guardando mundos…');const result=await api({action:'save',deck_id:draft.id,expected_revision:draft.revision,attributes:draft.attributes});accept(result);message('Mundos guardados. Principal: '+M.catalog[result.record.primary]+'.');}catch(e){message(e.message,true);}finally{busy=false;renderAll();}}
 async function changeMembership(id,operation){if(busy||!canEdit||!eligible.includes(Number(id)))return;const r=record(id);try{let attributes;if(operation==='add')attributes=M.add(r.attributes,world);else if(operation==='primary')attributes=M.primary(r.attributes,world);else attributes=M.remove(r.attributes,world);busy=true;renderAll();const result=await api({action:'save',deck_id:Number(id),expected_revision:r.revision,attributes});accept(result);status(operation==='remove'?'Deck quitado de este mundo. Sus otros mundos se conservan.':operation==='primary'?'Mundo principal actualizado.':'Deck añadido al mundo.');}catch(e){status(e.message,true);}finally{busy=false;renderAll();}}
 function normalizeRoute(){const aliases={atributos_decks:'mundos',atributos_deck_editar:'mundos_deck_editar'};const key=location.hash.slice(1);if(aliases[key]){const u=new URL(location.href);u.hash=aliases[key];if(u.searchParams.has('attribute_deck')){u.searchParams.set('world_deck',u.searchParams.get('attribute_deck'));u.searchParams.delete('attribute_deck');}root.history.replaceState(null,'',u);}}
 async function init(){
  list=document.querySelector('[data-screen="mundos"]');detail=document.querySelector('[data-screen="mundo_decks"]');form=document.querySelector('[data-attribute-form]');if(!list||!form||!detail)return;normalizeRoute();
  const requested=new URLSearchParams(location.search);world=Object.hasOwn(M.catalog,requested.get('world'))?requested.get('world'):'';
  list.querySelector('[data-world-search]').oninput=renderList;list.querySelector('[data-world-state]').onchange=renderList;list.querySelector('[data-world-clear]').onclick=()=>{list.querySelector('[data-world-search]').value='';list.querySelector('[data-world-state]').value='';renderList();};
  detail.querySelector('[data-world-candidate-search]').oninput=renderWorld;detail.querySelector('[data-world-member-search]').oninput=renderWorld;detail.querySelector('[data-world-membership]').onchange=renderWorld;detail.querySelector('[data-world-candidate]').onchange=candidateState;
  document.querySelectorAll('[data-attribute-refresh]').forEach(b=>b.onclick=refresh);form.onsubmit=save;form.querySelector('[data-attribute-deck]').onchange=e=>{if(!discard()){e.target.value=String(draft.id);return;}selectDeck(e.target.value);};
  document.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;
   if(b.matches('[data-world-open]'))openWorld(b.dataset.worldOpen);
   if(b.matches('[data-world-add]'))changeMembership(detail.querySelector('[data-world-candidate]').value,'add');
   if(b.matches('[data-world-remove]'))changeMembership(b.dataset.worldRemove,'remove');
   if(b.matches('[data-world-primary]'))changeMembership(b.dataset.worldPrimary,'primary');
   if(b.matches('[data-world-back]')&&!busy&&discard()){dirty=false;setQuery('world','');routeTo('mundos');}
   if(b.matches('[data-attribute-new]'))openEditor();
   if(b.matches('[data-attribute-edit]'))openEditor(b.dataset.attributeEdit);
   if(b.matches('[data-attribute-back]')&&!busy&&discard()){dirty=false;setQuery('world_deck','');if(returnWorld)openWorld(returnWorld);else routeTo('mundos');}
   try{let changed=false;if(b.matches('[data-attribute-add]')){draft.attributes=M.add(draft.attributes,b.dataset.attributeAdd);changed=true;}if(b.matches('[data-attribute-drop]')){draft.attributes=M.remove(draft.attributes,b.dataset.attributeDrop);changed=true;}if(b.matches('[data-attribute-promote]')){draft.attributes=M.primary(draft.attributes,b.dataset.attributePromote);changed=true;}if(changed){dirty=true;message('Cambios sin guardar.');renderEditor();}}catch(error){message(error.message,true);}
  });
  root.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
  root.addEventListener('hashchange',()=>{if(location.hash==='#mundo_decks'){const code=new URLSearchParams(location.search).get('world');world=Object.hasOwn(M.catalog,code)?code:'';renderWorld();}});
  if(await refresh()){if(location.hash==='#mundos_deck_editar'){returnWorld=world;await selectDeck(Number(requested.get('world_deck')||0));}else renderEditor();}
 }
 root.YgoDeckAttributes={renderDeck,refresh};document.addEventListener('DOMContentLoaded',init);
})(window);
