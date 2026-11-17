(function(root){
  'use strict';
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const zones={main:'Main Deck',extra:'Extra Deck',side:'Side Deck',reserve:'Reserva'};
  const names=['Empieza aquí','Identidad y versión','Lista y cantidades','Fuentes y zonas','Cartas y funciones','Motores y sinergias','Práctica y reglas','Interacciones y piezas iniciales','Fichas de combos','Guías visuales','Cadenas y resolución'];
  function inline(text){return esc(text).replace(/APARTADO_(\d{2})/g,(_,n)=>names[Number(n)-1]||`Apartado ${n}`).replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/`([^`]+)`/g,'<code>$1</code>').replace(/https:\/\/www\.db\.yugioh-card\.com\/[^\s<)]+/g,url=>`<a href="${url}" target="_blank" rel="noopener noreferrer">Fuente oficial</a>`);}
  function photo(p){return `<figure><button type="button" class="checked-photo" data-photo="${esc(p.image)}" data-name="${esc(p.name)}" aria-label="Ampliar ${esc(p.name)}"><img src="${esc(p.image)}" alt="${esc(p.name)}" width="100" height="145" loading="lazy"></button><figcaption>${esc(p.name)}${p.kind==='skill'?'<small>Skill · fuera del Main y de la mano</small>':''}</figcaption></figure>`;}
  function prose(text,photos){
    const out=[];let list='',table=false;
    const close=()=>{if(list){out.push(`</${list}>`);list='';}if(table){out.push('</tbody></table></div>');table=false;}};
    for(const line of text.split(/\r?\n/)){
      if(!line.trim()){close();continue;}
      if(/^\s*-?\s*\*\*(Estado|Rol del asistente|Siguiente|Recurso visual|Aplicación autorizada)/i.test(line)||/^#### Aplicación en Station X/.test(line))continue;
      if(line.startsWith('|')){if(/^\|[\s|:-]+\|?$/.test(line))continue;if(list){out.push(`</${list}>`);list='';}const cells=line.replace(/^\||\|$/g,'').split('|');if(!table){out.push('<div class="study-table"><table><tbody>');table=true;}out.push(`<tr>${cells.map(c=>`<td>${inline(c.trim())}</td>`).join('')}</tr>`);continue;}if(table)close();
      const heading=/^#{3,5}\s+(.*)/.exec(line);if(heading){close();out.push(`<h4>${inline(heading[1])}</h4>`);continue;}
      const image=/!\[[^\]]*\]\(([^)]+)\)/.exec(line);
      if(image){close();const p=photos.find(p=>p.image.split('/').at(-1)===image[1].split('/').at(-1));if(p)out.push(photo(p));continue;}
      const item=/^\s*(?:- |\d+\. )(.*)/.exec(line);
      if(item){const kind=/^\d+\./.test(line)?'ol':'ul';if(list!==kind){close();list=kind;out.push(`<${kind}>`);}out.push(`<li>${inline(item[1])}</li>`);}
      else{close();out.push(`<p>${inline(line)}</p>`);}
    }
    close();return out.join('');
  }
  function mount(panel,data,showCard){
    if(!data.sections)return;
    if(![7,10,11].includes(data.sections.length)||data.sections.some((s,i)=>s.number!==i+1))throw Error('Rango de apartados no compatible.');
    const extended=data.sections.length>=10,visible=extended?[2,4,5,6,7,8,9,10,...(data.sections.length===11?[11]:[])]:[1,2,3,4,5,6,7];
    const allPhotos=[...data.cards,...data.skills],section=n=>data.sections[n-1].source_text;
    const identity=data.identity;
    const sources=data.cards.map(c=>({card:c,url:(c.text.match(/https:\/\/www\.db\.yugioh-card\.com\/[^\s*]+/)||[])[0]})).filter(s=>s.url);
    const page=(n,html)=>`<section class="study-page" role="tabpanel" data-study-page="${n}" id="study-${data.deck_id}-${n}" aria-labelledby="study-tab-${data.deck_id}-${n}" hidden><h3>${names[n-1]}</h3>${html}</section>`;
    const nav=document.createElement('nav');nav.className='study-nav';nav.setAttribute('role','tablist');nav.setAttribute('aria-label','Apartados de estudio del deck');
    nav.innerHTML=visible.map(n=>`<button type="button" role="tab" id="study-tab-${data.deck_id}-${n}" data-study-tab="${n}" aria-controls="study-${data.deck_id}-${n}" aria-selected="false">${names[n-1]}</button>`).join('');
    panel.querySelector('.checked-hero').after(nav);
    const first=page(1,`<div class="study-intro">${Object.entries(data.goal).map(([title,text])=>`<section><h4>${esc(title==='Objetivo central'?'Qué aprenderás':title)}</h4><p>${inline(text)}</p></section>`).join('')}</div><button type="button" class="btn btn-outline-info" data-study-go="5">Conocer las cartas</button>`);
    const second=page(2,`<dl class="study-identity"><div><dt>Deck</dt><dd>${esc(identity.name)} · ID ${identity.deck_id}</dd></div><div><dt>Formato</dt><dd>${identity.format==='rush_duel'?'Rush Duel':'TCG'}</dd></div><div><dt>Equipo</dt><dd>${esc(identity.team||'No registrado')}</dd></div><div><dt>Documento recibido</dt><dd>${esc(identity.source_date)} · contrato ${esc(identity.source_contract)}</dd></div><div><dt>Inventario cotejado</dt><dd>${esc(identity.inventory_verified_on)}</dd></div></dl><p>Identidad vinculada al registro real de la plataforma. El estudio documental y la certificación de jugadas son estados diferentes.</p><details><summary>Detalle de recepción y procedencia</summary>${prose(section(2),allPhotos)}</details>`);
    const third=page(3,`<p>Lista guardada en la plataforma. Las copias y su zona se conservan.</p><button type="button" class="btn btn-outline-info" data-study-decklist>Abrir Decklist y multimedia</button>${Object.entries(zones).map(([zone,title])=>{const rows=data.inventory.filter(s=>s.section===zone);return `<details class="study-inventory" ${zone==='main'?'open':''}><summary>${title} · ${rows.reduce((n,r)=>n+r.quantity,0)} copias</summary>${rows.length?`<ul>${rows.map(r=>{const card=data.cards.find(c=>c.slots.some(s=>s.card_id===r.card_id));return `<li><b>×${r.quantity}</b> ${esc(r.name)} ${card?`<button type="button" data-study-card-cid="${card.cid}">Ver función</button>`:''}</li>`;}).join('')}</ul>`:'<p>Zona documentada sin cartas.</p>'}</details>`;}).join('')}<details><summary>Alcance de la validación del inventario</summary>${prose(section(3),allPhotos)}</details>`);
    const fourth=page(4,`<div class="study-zone-guide"><p><b>Main:</b> cartas de la lista principal, sujetas a sus condiciones de uso.</p><p><b>Extra:</b> requiere materiales y procedimiento de Invocación.</p><p><b>Side:</b> sustituciones entre duelos; no aporta cartas durante el duelo.</p><p><b>Reserva:</b> alternativas fuera de la lista activa.</p></div>${data.skills.map(s=>`<section class="study-skill"><h4>${esc(s.name)}</h4><p>Skill local separada: no se roba ni cuenta en Main, Extra, Side o Reserva. Sus requisitos y límites dependen del texto aportado y de las reglas de la liga.</p>${photo(s)}${prose(s.text,[])}</section>`).join('')}<details><summary>Fuentes por carta · ${sources.length} referencias</summary><ul>${sources.map(s=>`<li><a href="${esc(s.url)}" target="_blank" rel="noopener noreferrer">${esc(s.card.name)}</a></li>`).join('')}</ul></details><details><summary>Documentación de zonas recibida</summary>${prose(section(4),allPhotos)}</details>`);
    const sixth=page(6,`<p>Relaciones documentadas de búsqueda, materiales y recursos. Cada mapa conserva sus condiciones; no representa un combo completo ni una ejecución certificada.</p><label>Elegir relación<select class="form-select" data-study-map>${data.engine_maps.map((m,i)=>`<option value="${i}">${esc(m.title)}</option>`).join('')}</select></label><article class="study-map" data-study-map-detail></article><details><summary>Explicación completa de motores, dependencias y conflictos</summary>${prose(data.engine_notes,allPhotos)}</details>`);
    const seventh=page(7,`<p>Reglas y comprobaciones documentadas para esta construcción y su formato. No certifican por sí solas una secuencia completa.</p>${prose(section(7),allPhotos)}<button type="button" class="btn btn-outline-info" data-study-go="5">Consultar requisitos de una carta</button>`);
    panel.querySelector('.checked-provenance').insertAdjacentHTML('beforebegin',(extended?'':first)+second+(extended?'':third)+fourth+sixth+seventh);
    const cards=panel.querySelector('[data-study-page="5"]');cards.setAttribute('role','tabpanel');cards.id=`study-${data.deck_id}-5`;cards.setAttribute('aria-labelledby',`study-tab-${data.deck_id}-5`);
    function go(n){for(const p of panel.querySelectorAll('[data-study-page]'))p.hidden=Number(p.dataset.studyPage)!==n;for(const tab of nav.querySelectorAll('button')){const current=Number(tab.dataset.studyTab)===n;tab.setAttribute('aria-selected',String(current));tab.tabIndex=current?0:-1;}}
    nav.onclick=e=>{const tab=e.target.closest('[data-study-tab]');if(tab)go(Number(tab.dataset.studyTab));};
    nav.onkeydown=e=>{if(!['ArrowRight','ArrowLeft','Home','End'].includes(e.key))return;const current=visible.indexOf(Number(nav.querySelector('[aria-selected="true"]').dataset.studyTab)),next=e.key==='Home'?visible[0]:e.key==='End'?visible.at(-1):visible[(current+(e.key==='ArrowRight'?1:visible.length-1))%visible.length];e.preventDefault();go(next);nav.querySelector(`[data-study-tab="${next}"]`).focus();};
    panel.querySelectorAll('[data-study-go]').forEach(b=>b.onclick=()=>go(Number(b.dataset.studyGo)));
    panel.querySelectorAll('[data-study-card-cid]').forEach(b=>b.onclick=()=>{go(5);showCard(Number(b.dataset.studyCardCid));});
    panel.querySelector('[data-study-decklist]')?.addEventListener('click',()=>document.querySelector('[data-deck-category-open="decklist"]')?.click());
    const map=panel.querySelector('[data-study-map]');
    function updateMap(){const m=data.engine_maps[Number(map.value)];panel.querySelector('[data-study-map-detail]').innerHTML=`<h4>${inline(m.title)}</h4><div class="study-map-photos">${m.photos.map(photo).join('')}</div><h4>Qué permite</h4><p>${inline(m.result)}</p><h4>Requisitos y límites</h4><p>${inline(m.requirements)}</p><small>Correspondencia de cartas y fotografías comprobada. Comprobación secuencial de jugadas pendiente.</small>`;}
    map.onchange=updateMap;updateMap();
    if(extended)root.YgoStudyEleven.mount(panel,data,{esc,inline,photo,prose,page,go});
    go(extended?2:1);
  }
  function mountChecked(panel,data){
    const page=(n,title,body)=>`<section class="study-page" role="tabpanel" data-study-page="${n}" id="study-${data.deck_id}-${n}" aria-labelledby="study-tab-${data.deck_id}-${n}" hidden><h3>${title}</h3>${body}</section>`;
    const tabs=[[5,'Cartas y funciones']];
    if(data.checked_interactions.length)tabs.push([8,'Interacciones y límites']);
    if(data.checked_cases.length)tabs.push([11,'Activación y resolución']);
    const nav=document.createElement('nav');nav.className='study-nav';nav.setAttribute('role','tablist');nav.setAttribute('aria-label','Contenido con comprobación individual');
    nav.innerHTML=tabs.map(([n,title])=>`<button type="button" role="tab" id="study-tab-${data.deck_id}-${n}" data-study-tab="${n}" aria-controls="study-${data.deck_id}-${n}" aria-selected="false">${title}</button>`).join('');
    panel.querySelector('.checked-hero').after(nav);
    const cardPage=panel.querySelector('[data-study-page="5"]');cardPage.setAttribute('role','tabpanel');cardPage.id=`study-${data.deck_id}-5`;cardPage.setAttribute('aria-labelledby',`study-tab-${data.deck_id}-5`);
    if(data.checked_interactions.length)panel.querySelector('.checked-provenance').insertAdjacentHTML('beforebegin',page(8,'Interacciones y límites',data.checked_interactions.map(u=>`<article class="study-map"><h4>${u.kind==='blocker'?'Limitación comprobada':'Interacción comprobada'}</h4><p>${inline(u.title)}</p><h4>${u.kind==='blocker'?'Qué falta para utilizarla':'Qué permite'}</h4><p>${inline(u.result)}</p><h4>Requisitos y límites</h4><p>${inline(u.requirements)}</p><small>Esta comprobación puntual no certifica un combo completo.</small></article>`).join('')));
    if(data.checked_cases.length)panel.querySelector('.checked-provenance').insertAdjacentHTML('beforebegin',page(11,'Activación y resolución',`<p>Comprobaciones puntuales con evidencia enlazada. Se conservan sus condiciones; no equivalen a combos completos certificados.</p>${data.checked_cases.map(u=>`<details><summary>${inline(u.title)}</summary>${prose(u.text,[])}<h4>Fuentes primarias citadas en el expediente</h4><ul>${u.sources.map(url=>`<li><a href="${esc(url)}" target="_blank" rel="noopener noreferrer">Consultar fuente oficial</a></li>`).join('')}</ul></details>`).join('')}`));
    function go(n){panel.querySelectorAll('[data-study-page]').forEach(p=>p.hidden=Number(p.dataset.studyPage)!==n);nav.querySelectorAll('button').forEach(b=>{const selected=Number(b.dataset.studyTab)===n;b.setAttribute('aria-selected',String(selected));b.tabIndex=selected?0:-1;});}
    nav.onclick=e=>{const b=e.target.closest('[data-study-tab]');if(b)go(Number(b.dataset.studyTab));};
    nav.onkeydown=e=>{if(!['ArrowRight','ArrowLeft','Home','End'].includes(e.key))return;const i=tabs.findIndex(([n])=>n===Number(nav.querySelector('[aria-selected="true"]').dataset.studyTab));const next=e.key==='Home'?0:e.key==='End'?tabs.length-1:(i+(e.key==='ArrowRight'?1:tabs.length-1))%tabs.length;e.preventDefault();go(tabs[next][0]);nav.querySelector(`[data-study-tab="${tabs[next][0]}"]`).focus();};
    const decklist=document.createElement('button');decklist.type='button';decklist.className='btn btn-outline-info';decklist.textContent='Consultar Decklist y multimedia';decklist.onclick=()=>document.querySelector('[data-deck-category-open="decklist"]')?.click();panel.querySelector('.checked-hero').append(decklist);
    go(5);
  }
  root.YgoStudySections={mount,mountChecked};
})(window);
