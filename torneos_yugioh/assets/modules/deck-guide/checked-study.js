(function (root) {
  'use strict';
  const cache = new Map();
  const escape = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const zones = {main:'Main Deck', extra:'Extra Deck', side:'Side Deck', reserve:'Reserva'};
  function inline(text) {
    return escape(text).replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/https:\/\/www\.db\.yugioh-card\.com\/[^\s<]+/g, url => `<a href="${url}" target="_blank" rel="noopener noreferrer">Consultar fuente oficial</a>`)
      .replace(/\n/g, '<br>');
  }
  function load(url) {
    if (!cache.has(url)) cache.set(url, fetch(url, {cache:'no-store'}).then(r => {
      if (!r.ok) throw Error('No se pudieron cargar las fichas.');
      return r.json();
    }).catch(error => {cache.delete(url); throw error;}));
    return cache.get(url);
  }
  function detail(card) {
    const role = card.fields.find(f => /papel|funcional/i.test(f.title));
    const rest = card.fields.filter(f => f !== role && !/zona y copias/i.test(f.title));
    return `<div class="checked-card-heading"><button type="button" class="checked-photo" data-photo="${escape(card.image)}" data-name="${escape(card.name)}" aria-label="Ampliar ${escape(card.name)}"><img src="${escape(card.image)}" alt="${escape(card.name)}" width="146" height="212"></button><div><h3>${escape(card.name)}</h3><p>${card.slots.map(s => `${zones[s.section]} · ×${s.quantity}`).join(' / ')}</p>${card.slots.every(s => s.section==='reserve') ? '<p class="checked-notice">Solo en Reserva: no está disponible durante el duelo actual.</p>' : ''}${role ? `<p>${inline(role.text)}</p>` : ''}<small>Ficha editorial documentada · no certifica una secuencia de combo.</small></div></div><div class="checked-fields">${rest.map((f,i) => `<details ${i===0?'open':''}><summary>${escape(f.title)}</summary><p>${inline(f.text)}</p></details>`).join('')}</div>`;
  }
  async function render(host, deck, entry, isCurrent) {
    const data = await load(entry.path);
    if (!isCurrent()) return;
    if(data.schema==='station-x-curated-v62')return root.YgoCuratedStudy.render(host,deck,data,entry);
    if (Number(data.deck_id)!==Number(deck.id) || data.cards.length!==entry.cards || data.combos.length || data.engine_relations.length) throw Error('Contenido no compatible con el deck.');
    let panel = host.querySelector('[data-checked-study]');
    if (!panel) {panel=document.createElement('section');panel.dataset.checkedStudy='';host.prepend(panel);}
    for (const child of host.children) {
      child.hidden = child!==panel && !child.classList.contains('deck-strategy-editor');
      child.style.display = child.hidden ? 'none' : '';
    }
    const studyKey=`${deck.id}:${entry.sha256||entry.path}`;
    // Native background refreshes must not discard the route/step being read.
    if(panel.dataset.studyKey===studyKey)return;
    const counts = Object.fromEntries(Object.keys(zones).map(z => [z, data.inventory.filter(s=>s.section===z).reduce((n,s)=>n+s.quantity,0)]));
    panel.innerHTML = `<header class="checked-hero"><span>Estilo y estrategia · ${data.format==='rush_duel'?'Rush Duel':'TCG'}</span><h2>Conoce las cartas de ${escape(data.name)}</h2><p>Consulta qué hace cada carta, qué necesita y qué recursos utiliza.</p><div class="checked-counts">${Object.keys(zones).map(z=>`<span>${zones[z]} <b>${counts[z]}</b></span>`).join('')}</div><p class="checked-notice">${data.cards.length} fichas documentadas. Los combos completos y las relaciones de motor pendientes todavía no están cargados.</p></header><div class="checked-controls"><label>Buscar carta o efecto<input type="search" class="form-control" placeholder="Nombre, efecto, coste o recurso" data-checked-search></label><label>Zona<select class="form-select" data-checked-zone><option value="">Todas las zonas</option>${Object.keys(zones).map(z=>`<option value="${z}">${zones[z]}</option>`).join('')}</select></label><span data-checked-count aria-live="polite"></span></div><div class="checked-layout"><div class="checked-cards" aria-label="Cartas documentadas"></div><article class="checked-detail" aria-live="polite"></article></div><details class="checked-provenance"><summary>Procedencia y alcance del contenido</summary><p>Fuente: ${escape(data.source_file)} · apartados 05 y 08. Los checks respaldan las fichas editoriales y su función, sin certificar combos, legalidad vigente o traducciones reglamentarias.</p><p>Las cantidades y zonas corresponden al inventario guardado; Side y Reserva permanecen separados.</p></details><dialog class="checked-zoom"><button type="button" class="btn btn-outline-info" data-close-photo>Cerrar imagen</button><img alt=""><p></p></dialog>`;
    let selected=data.cards[0]?.id;
    const list=panel.querySelector('.checked-cards'), inspector=panel.querySelector('.checked-detail'), counter=panel.querySelector('[data-checked-count]');
    function update() {
      const query=panel.querySelector('[data-checked-search]').value.toLocaleLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,''), zone=panel.querySelector('[data-checked-zone]').value;
      const filtered=data.cards.filter(c=>(!zone||c.slots.some(s=>s.section===zone))&&(!query||`${c.name} ${c.text}`.toLocaleLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,'').includes(query)));
      if (!filtered.some(c=>c.id===selected)) selected=filtered[0]?.id;
      counter.textContent=`${filtered.length} de ${data.cards.length} cartas`;
      list.innerHTML=filtered.map(c=>`<button type="button" class="checked-card ${c.id===selected?'is-selected':''}" data-checked-card="${escape(c.id)}" aria-pressed="${c.id===selected}"><img src="${escape(c.image)}" alt="" loading="lazy" width="74" height="108"><span>${escape(c.name)}<small>${c.slots.map(s=>`${zones[s.section]} ×${s.quantity}`).join(' · ')}</small></span></button>`).join('');
      const card=filtered.find(c=>c.id===selected);inspector.innerHTML=card?detail(card):'<p>No hay cartas con esos filtros.</p>';
    }
    panel.querySelector('[data-checked-search]').addEventListener('input',update);
    panel.querySelector('[data-checked-zone]').addEventListener('change',update);
    panel.onclick=event=>{
      const card=event.target.closest('[data-checked-card]'); if(card){selected=card.dataset.checkedCard;update();}
      const photo=event.target.closest('[data-photo]'), dialog=panel.querySelector('dialog');
      if(photo){dialog.querySelector('img').src=photo.dataset.photo;dialog.querySelector('img').alt=photo.dataset.name;dialog.querySelector('p').textContent=photo.dataset.name;dialog.showModal();}
      if(event.target.closest('[data-close-photo]'))dialog.close();
    };
    update();
    if(data.eligibility_policy==='checked-only-v1'){
      const cardPage=document.createElement('section');cardPage.dataset.studyPage='5';cardPage.className='study-page';
      panel.querySelector('.checked-provenance').before(cardPage);cardPage.append(panel.querySelector('.checked-controls'),panel.querySelector('.checked-layout'));
      panel.querySelector('.checked-hero h2').textContent=`Estudia ${data.name}`;
      panel.querySelector('.checked-hero p').textContent='Cartas y comprobaciones individuales de esta construcción.';
      panel.querySelector('.checked-hero .checked-notice').textContent=`${data.cards.length} fichas de cartas · ${data.checked_interactions.length} interacciones o límites · ${data.checked_cases.length} casos de resolución.`;
      panel.querySelector('.checked-provenance p').textContent=`Fuente: ${data.source_file}. Solo se muestran unidades con comprobación individual y evidencia vinculada. Las rutas sin certificación integral permanecen en el archivo de origen, fuera del contenido activo.`;
      root.YgoStudySections.mountChecked(panel,data);
    }else if(data.sections){
      const cardPage=document.createElement('section');cardPage.dataset.studyPage='5';cardPage.className='study-page';
      panel.querySelector('.checked-provenance').before(cardPage);
      cardPage.append(panel.querySelector('.checked-controls'),panel.querySelector('.checked-layout'));
      panel.querySelector('.checked-hero h2').textContent=`Estudia ${data.name}`;
      panel.querySelector('.checked-hero p').textContent='Cartas, motores, interacciones, combos y reglas de esta construcción.';
      panel.querySelector('.checked-hero .checked-notice').textContent=`Apartados 01–${String(data.sections.length).padStart(2,'0')} · ${data.cards.length} fichas de cartas · ${data.routes?.length||0} rutas documentadas · verificación integral pendiente.`;
      panel.querySelector('.checked-provenance p').textContent=`Fuente: ${data.source_file} · apartados 01–${String(data.sections.length).padStart(2,'0')}. Las comprobaciones de identidad e inventario no equivalen a certificación de jugadas.`;
      root.YgoStudySections.mount(panel,data,cid=>{const card=data.cards.find(c=>c.cid===cid);if(card){selected=card.id;panel.querySelector('[data-checked-search]').value='';panel.querySelector('[data-checked-zone]').value='';update();}});
    }
    panel.dataset.studyKey=studyKey;
  }
  root.YgoCheckedStudy={render};
})(window);
