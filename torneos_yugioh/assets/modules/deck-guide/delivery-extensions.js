(function(root){
  'use strict';
  // Public gameplay projections only. Source audits, manifests and previews
  // remain outside the reader; all values are escaped by its existing helpers.
  function fields(rows,h){return `<dl class="curated-resources">${Object.entries(rows).filter(([,v])=>v).map(([k,v])=>`<div><dt>${h.esc(k)}</dt><dd>${h.text(Array.isArray(v)?v.join('\n'):v)}</dd></div>`).join('')}</dl>`;}
  const list=(rows,h)=>`<ul>${(rows||[]).map(s=>`<li>${h.text(s)}</li>`).join('')}</ul>`;
  function causalCard(c,h){
    const p=c.causal_profile;if(!p)return '';
    return `<details data-causal-card><summary>Funciones y conexiones causales</summary>${list(p.funciones.map(f=>f.comprobacion),h)}<p>${h.text(p.campo_de_uso)}</p>${p.relacion_primaria==='ROL_AUTONOMO_SIN_PAREJA_PROBADA'?'<p>Función autónoma: no hay una pareja de cartas demostrada para esta función.</p>':''}${p.relacion_primaria==='RECURSO_DE_BANQUILLO_O_RESERVA'?'<p>Side o Reserva: requiere un cambio explícito antes de utilizarla en el duelo.</p>':''}</details>`;
  }
  function mountV21(data,content,h){
    if(!data.delivery_v21)return;
    const availability=photos=>`<ul>${photos.map(c=>`<li>${h.esc(c.name)}: ${c.slots.map(s=>`${({main:'Main',extra:'Extra',side:'Side',reserve:'Reserva'})[s.section]} ×${s.quantity}`).join(' · ')}</li>`).join('')}</ul>`;
    const engines=content('cards').querySelector('.curated-engines');
    engines.innerHTML=data.causal_relations.map(r=>`<details data-causal-relation><summary>${h.esc(r.origen.nombre)} → ${h.esc(r.destino.nombre)}</summary>${h.strip(r.photos)}${availability(r.photos)}<p>${h.text(r.interaccion)}</p><p>${h.text(r.control)}</p>${r.fuentes.map(url=>`<p><a href="${h.esc(url)}" target="_blank" rel="noopener noreferrer">Fuente oficial</a></p>`).join('')}</details>`).join('');
    if(data.causal_skills.length)engines.insertAdjacentHTML('beforeend',`<details><summary>Conexiones con la Skill externa</summary>${data.causal_skills.map(s=>`<h4>${h.esc(s.skill)}</h4><p>${h.text(s.explicacion)}</p>`).join('')}</details>`);
    // Raw rule-variant audit remains in the source, never in the player overview.
    // The player strategy is rendered from player_guide; no legacy decision audit is mounted.

  }
  function card(c,h){
    if(!c.delivery_fields)return '';
    const identity=c.identity;
    const labels={CONDICION_Y_VENTANA:'Condición y ventana',COSTE_Y_RESOLUCION:'Coste y resolución',LIMITES:'Límites',RECURSOS:'Recursos',CADENA:'Cadena',RESULTADO:'Resultado'};
    const contract=c.rule_contract;
    return `${identity?`<p data-card-identity>${h.esc(identity.name)} · ${h.esc(identity.format.toUpperCase())}</p>`:''}<details class="delivery-details"><summary>Uso, ventanas y relaciones de esta carta</summary>${fields(c.delivery_fields,h)}${c.a06_classification?`<h4>Función y relaciones</h4><p>${h.text(c.a06_classification.razon)}</p>${(c.a06_classification.relaciones||[]).map(r=>`<p>${h.text(r.operacion)}</p>`).join('')}`:''}</details>${contract?`<details data-rule-contract><summary>Reglas y momento de uso</summary><p>Control documentado · prueba en motor pendiente.</p>${contract.verificaciones_minimas.map(r=>`<section><h4>${h.esc(labels[r.tipo]||'Comprobación')}</h4><p>${h.text(r.informacion_existente==='PENDIENTE_REPLAY'?'Resultado pendiente de comprobar en partida.':r.informacion_existente)}</p><p>${h.text(r.accion)}</p></section>`).join('')}<details><summary>Preguntas para esta carta</summary>${(contract.reglas_orientativas_por_senales||[]).map(r=>`<p>${h.text(r.pregunta_de_legalidad)}</p>`).join('')}</details></details>`:''}`;
  }
  function route(r,h){
    const branches=[['Alternativas',r.variants],['Si el rival interrumpe',r.interruptions]];
    return `${r.infographic?`<details class="delivery-infographic"><summary>Ver guía visual completa</summary><a href="${h.esc(r.infographic)}" target="_blank" rel="noopener">Abrir imagen a tamaño completo</a><img src="${h.esc(r.infographic)}" alt="Guía de ${h.esc(r.title)}" loading="lazy"><p>La lámina ilustra la secuencia documentada; consulta las condiciones de la ruta.</p></details>`:''}${branches.filter(([,v])=>v?.length).map(([t,v])=>`<details class="delivery-details"><summary>${t}</summary>${list(v,h)}</details>`).join('')}${r.plans?`<details class="delivery-details"><summary>Plan primero y segundo</summary>${fields({'Primero':r.plans.first,'Segundo':r.plans.second},h)}</details>`:''}${r.sources?.length?`<details class="delivery-details"><summary>Fuentes de la secuencia</summary>${r.sources.map((url,i)=>`<p><a href="${h.esc(url)}" target="_blank" rel="noopener noreferrer">Texto oficial ${i+1}</a></p>`).join('')}</details>`:''}`;
  }
  function mount(data,content,h){
    // The imported HTML reader is an archival artifact, not a public guide link.
    mountV21(data,content,h);
    const surface=content('cards').closest('[data-checked-study]');
    if(surface){
      if(surface._deliveryImageError)surface.removeEventListener('error',surface._deliveryImageError,true);
      surface._deliveryImageError=event=>{
        const img=event.target;if(img.tagName!=='IMG'||img.dataset.imageFailed)return;
        const c=data.cards.find(c=>new URL(c.image,document.baseURI).href===img.src);if(!c)return;
        img.dataset.imageFailed='1';img.alt=`${c.name}`;
        const notice=document.createElement('p');notice.dataset.cardImageError='';notice.textContent=`No se pudo cargar la imagen de ${c.name}.`;img.after(notice);
      };
      surface.addEventListener('error',surface._deliveryImageError,true);
    }
    // Audit, general rules, historical dossier and editorial controls are private evidence, not lessons.
    if(data.side_plans?.length){
      content('plans').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Planes de Side · ${data.side_plans.length}</summary><p>Son alternativas para preparar otra partida. Ningún intercambio está aplicado; no se suman todas las propuestas ni se usa una misma copia dos veces.</p><div class="curated-scenarios">${data.side_plans.map(p=>`<article><h4>${h.esc(p.card)}</h4><p><b>Contra:</b> ${h.text(p.matchup)}</p>${h.strip([p.photo,...(p.leaving_photo?[p.leaving_photo]:[])])}${fields({'Entra':p.entering,'Sale':p.leaving||'Carta saliente aún sin elegir','Qué ganas y qué sacrificas':p.tactical_gain_and_tradeoff,'Momento y coste':p.activation_and_resource_note},h)}${p.outgoing_candidate?`<details><summary>Posible salida ? sin elegir</summary>${fields({'Carta candidata':p.outgoing_candidate.outgoing_name,'Motivo':p.outgoing_candidate.reason,'Qu? pierdes':p.outgoing_candidate.loss,'Requisitos':p.outgoing_candidate.prerequisites},h)}</details>`:''}${!p.exact_swap_confirmed_by_source?'<p>La salida del Extra requiere una elección: este plan no define todavía un intercambio completo.</p>':''}<details><summary>Antes de elegir el cambio</summary>${list(p.test_questions,h)}</details></article>`).join('')}</div></details>`);
    }
    if(data.matchup_plans?.length)content('plans').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Planes según el rival · ${data.matchup_plans.length}</summary><div class="curated-scenarios">${data.matchup_plans.map(p=>`<article><h4>${h.esc(p.rival)}</h4><p>${h.text(p.situacion)}</p><ol>${(p.orden_de_juego||[]).map(s=>`<li>${h.text(s)}</li>`).join('')}</ol>${p.entrada_propuesta?.length?`<p><b>Propuesta de entrada:</b> ${h.esc(p.entrada_propuesta.map(c=>`${data.cards.find(x=>x.cid===c.cid)?.name||'Carta sin identificar'} ×${c.copias} (${c.zona_origen})`).join('; '))}</p>`:''}${p.salida_propuesta?.length?`<p><b>Propuesta de salida:</b> ${h.esc(p.salida_propuesta.map(c=>`${data.cards.find(x=>x.cid===c.cid)?.name||'Carta sin identificar'} ×${c.copias} (${c.zona_origen})`).join('; '))}</p>`:''}${fields({'Qué pierdes con el cambio':p.criterio_corte,'Con la lista actual':p.si_no_hay_cambio},h)}<p>Las propuestas de cartas no están aplicadas.</p></article>`).join('')}</div></details>`);
    if(data.improvement_experiments?.length)content('practice').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Pruebas de mejora · ${data.improvement_experiments.length}</summary>${data.improvement_experiments.map(p=>`<article><h4>${h.esc(p.card)}</h4><p>Propuesta: ${h.esc(p.existing_quantity)} → ${h.esc(p.proposed_quantity)} copias. Sin aplicar.</p><p>${h.text(p.existing_rationale)}</p>${fields({'Pregunta':p.exercise?.question,'Método':p.exercise?.method,'Cuándo rechazarla':p.exercise?.failure_case},h)}</article>`).join('')}</details>`);
  }
  root.YgoDeliveryExtensions={card,causalCard,route,mount};
  if(typeof module==='object'&&module.exports)module.exports=root.YgoDeliveryExtensions;
})(typeof window!=='undefined'?window:globalThis);
