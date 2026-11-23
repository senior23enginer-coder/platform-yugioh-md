(function(root){
  'use strict';
  // Public gameplay projections only. Source audits, manifests and previews
  // remain outside the reader; all values are escaped by its existing helpers.
  function fields(rows,h){return `<dl class="curated-resources">${Object.entries(rows).filter(([,v])=>v).map(([k,v])=>`<div><dt>${h.esc(k)}</dt><dd>${h.text(Array.isArray(v)?v.join('\n'):v)}</dd></div>`).join('')}</dl>`;}
  const list=(rows,h)=>`<ul>${(rows||[]).map(s=>`<li>${h.text(s)}</li>`).join('')}</ul>`;
  function causalCard(c,h){
    const p=c.causal_profile;if(!p)return '';
    return `<details data-causal-card><summary>Funciones y conexiones causales</summary><p>${h.esc(c.causal_review_label)}</p>${list(p.funciones.map(f=>f.comprobacion),h)}<p>${h.text(p.campo_de_uso)}</p>${p.relacion_primaria==='ROL_AUTONOMO_SIN_PAREJA_PROBADA'?'<p>Función autónoma: no hay una pareja de cartas demostrada para esta función.</p>':''}${p.relacion_primaria==='RECURSO_DE_BANQUILLO_O_RESERVA'?'<p>Side o Reserva: requiere un cambio explícito antes de utilizarla en el duelo.</p>':''}<p>${h.text(p.no_inferir)}</p></details>`;
  }
  function mountV21(data,content,h){
    if(!data.delivery_v21)return;
    const availability=photos=>`<ul>${photos.map(c=>`<li>${h.esc(c.name)} · CID ${c.cid}: ${c.slots.map(s=>`${({main:'Main',extra:'Extra',side:'Side',reserve:'Reserva'})[s.section]} ×${s.quantity}`).join(' · ')}</li>`).join('')}</ul>`;
    const engines=content('cards').querySelector('.curated-engines');
    engines.innerHTML=data.causal_relations.map(r=>`<details data-causal-relation><summary>${h.esc(r.origen.nombre)} → ${h.esc(r.destino.nombre)}</summary>${h.strip(r.photos)}${availability(r.photos)}<p>${h.text(r.interaccion)}</p><p>${h.text(r.control)}</p><p>Relación documental; no reproducida en motor.</p>${r.fuentes.map(url=>`<p><a href="${h.esc(url)}" target="_blank" rel="noopener noreferrer">Fuente oficial</a></p>`).join('')}</details>`).join('');
    if(data.causal_skills.length)engines.insertAdjacentHTML('beforeend',`<details><summary>Conexiones con la Skill externa</summary>${data.causal_skills.map(s=>`<h4>${h.esc(s.skill)}</h4><p>${h.text(s.explicacion)}</p>`).join('')}</details>`);
    content('intro').insertAdjacentHTML('beforeend',`<details data-variant-control><summary>Explorar variantes de reglas</summary><p>Prueba pendiente en motor completo. Estas variantes son condiciones de estudio.</p><label>Caso<select class="form-select" data-variant-case></select></label><label>Situación<select class="form-select" data-variant-kind><option value="LINEA_BASE">Línea base</option><option value="RECURSO_O_OBJETIVO_AUSENTE">Falta un recurso u objetivo</option><option value="RESPUESTA_EN_CADENA">Respuesta rival en Cadena</option></select></label><div data-variant-detail aria-live="polite"></div></details>`);
    const controls=content('intro').querySelector('[data-variant-control]'),caseSelect=controls.querySelector('[data-variant-case]'),kind=controls.querySelector('[data-variant-kind]');
    const unique=[...new Map(data.rule_variants.map(r=>[r.caso_original,r])).values()];
    caseSelect.innerHTML=unique.map(r=>`<option value="${h.esc(r.caso_original)}">${h.esc(r.codigo.replace(/_/g,' '))}</option>`).join('');
    const showVariant=()=>{
      const row=data.rule_variants.find(r=>r.caso_original===caseSelect.value&&r.variante===kind.value);
      controls.querySelector('[data-variant-detail]').innerHTML=`<article><h4>${h.esc(row.codigo.replace(/_/g,' '))}</h4><p>${h.text(row.regla_o_caso)}</p><h5>Estado que debes preparar</h5><p>${h.text(row.entrada)}</p><h5>Datos que faltan para reproducirlo</h5>${list(row.informacion_pendiente_para_replay,h)}<p>Costes, ventanas y CL1/CL2: deben concretarse para el estado real de este caso.</p><p>Resultado y situación posterior: pendientes de prueba; no se completa un campo imaginario.</p></article>`;
    };caseSelect.addEventListener('change',showVariant);kind.addEventListener('change',showVariant);showVariant();
    const titles={PRIMERO:'Ir primero',SEGUNDO:'Ir segundo',RESPUESTA_RIVAL:'Respuesta rival',RECURSOS:'Recursos',MANO_IRREGULAR:'Mano irregular',HABILIDAD_O_CONTINUIDAD:'Skill/continuidad',TURNO_TARDIO:'Turno tardío'};
    content('plans').insertAdjacentHTML('afterbegin',`<section data-situational-strategy><h4>Elige tu situación</h4><p>Recomendaciones documentales para este deck; confirma las condiciones antes de jugar.</p><div class="v21-actions" role="group" aria-label="Situación de juego">${Object.entries(titles).map(([key,title])=>`<button type="button" class="btn btn-outline-info" data-situation="${key}" aria-pressed="false">${title}</button>`).join('')}</div><div data-situation-detail aria-live="polite"></div></section>`);
    const strategies=content('plans').querySelector('[data-situational-strategy]');
    const showSituation=key=>{
      const row=data.situational_decisions.find(r=>r.escenario===key);
      strategies.querySelectorAll('[data-situation]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.situation===key)));
      strategies.querySelector('[data-situation-detail]').innerHTML=`<article><h4>${h.esc(titles[key])} · ${h.esc(data.name)}</h4><h5>Objetivo y jugada sugerida</h5><p>${h.text(row.linea_preferida_condicionada)}</p>${h.strip(row.photos)}${availability(row.photos)}<h5>Condiciones</h5><p>${h.text(row.estado_inicial_que_debe_confirmarse)}</p><h5>Respuesta rival y riesgo</h5><p>${h.text(row.riesgo_y_respuesta)}</p><h5>Alternativa</h5><p>${h.text(row.alternativa)}</p><details><summary>Preparar la práctica y comprobar el resultado</summary>${list(row.informacion_obligatoria,h)}<p>${h.text(row.indicador_para_prueba)}</p><p>El campo final exacto no está especificado en esta ficha: debe registrarse al probarla.</p></details><p>Side y Reserva no pasan a la mano automáticamente. La Skill es externa.</p></article>`;
    };
    strategies.addEventListener('click',event=>{const b=event.target.closest('[data-situation]');if(b)showSituation(b.dataset.situation);});showSituation('PRIMERO');
  }
  function card(c,h){
    if(!c.delivery_fields)return '';
    const identity=c.identity;
    const labels={CONDICION_Y_VENTANA:'Condición y ventana',COSTE_Y_RESOLUCION:'Coste y resolución',LIMITES:'Límites',RECURSOS:'Recursos',CADENA:'Cadena',RESULTADO:'Resultado'};
    const contract=c.rule_contract;
    return `${identity?`<p data-card-identity>${h.esc(identity.name)} · CID ${identity.cid} · ${h.esc(identity.format.toUpperCase())}</p>`:''}<details class="delivery-details"><summary>Uso, ventanas y relaciones de esta carta</summary>${fields(c.delivery_fields,h)}${c.a06_classification?`<h4>Función y relaciones</h4><p>${h.text(c.a06_classification.razon)}</p>${(c.a06_classification.relaciones||[]).map(r=>`<p>${h.text(r.operacion)}</p>`).join('')}`:''}</details>${contract?`<details data-rule-contract><summary>Reglas y momento de uso</summary><p>Control documentado · prueba en motor pendiente.</p>${contract.verificaciones_minimas.map(r=>`<section><h4>${h.esc(labels[r.tipo]||'Comprobación')}</h4><p>${h.text(r.informacion_existente==='PENDIENTE_REPLAY'?'Resultado pendiente de comprobar en partida.':r.informacion_existente)}</p><p>${h.text(r.accion)}</p></section>`).join('')}<details><summary>Preguntas para esta carta</summary>${(contract.reglas_orientativas_por_senales||[]).map(r=>`<p>${h.text(r.pregunta_de_legalidad)}</p>`).join('')}</details></details>`:''}`;
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
        img.dataset.imageFailed='1';img.alt=`${c.name} · CID ${c.cid}`;
        const notice=document.createElement('p');notice.dataset.cardImageError='';notice.textContent=`No se pudo cargar la imagen de ${c.name} (CID ${c.cid}).`;img.after(notice);
      };
      surface.addEventListener('error',surface._deliveryImageError,true);
    }
    if(data.delivery_progress)content('intro').insertAdjacentHTML('beforeend',`<details data-delivery-progress><summary>Cobertura y revisión de esta entrega</summary><p>Estos indicadores corresponden a los 11 decks. Cada validación mide el criterio indicado; no se promedian.</p>${data.delivery_progress.map(p=>`<section><h4>${h.esc(p.nombre)}</h4><p>Cobertura documental: ${p.porcentaje_cobertura_documental}% · ${h.esc(p.fraccion)}</p><p>Validación observada: ${p.porcentaje_validacion_observada}% · ${h.esc(p.fraccion_validacion)}</p><p>${h.text(p.criterio_validacion)}</p></section>`).join('')}</details>`);
    const scenarios=(Array.isArray(data.rule_scenarios)?data.rule_scenarios:[]).filter(s=>s&&String(s.situacion||'').trim()&&String(s.prueba_de_legalidad||'').trim());
    const generalRules=(Array.isArray(data.general_rules)?data.general_rules:[]).filter(r=>r&&String(r.detalle||'').trim());
    if(scenarios.length)content('intro').insertAdjacentHTML('beforeend',`<details data-rule-scenarios><summary>Reglas y situaciones de este deck</summary><p>Controles documentados; no son replays certificados.</p>${scenarios.map(s=>`<article><h4>${h.esc(s.situacion)}</h4><p>${h.text(s.prueba_de_legalidad)}</p></article>`).join('')}${generalRules.length?`<details><summary>Reglas generales y fuentes</summary>${generalRules.map(r=>`<p>${h.text(r.detalle)}${r.url?` <a href="${h.esc(r.url)}" target="_blank" rel="noopener noreferrer">Fuente oficial</a>`:''}</p>`).join('')}</details>`:''}</details>`);
    function explain(value){
      if(Array.isArray(value))return `<ul>${value.map(v=>`<li>${explain(v)}</li>`).join('')}</ul>`;
      if(value&&typeof value==='object')return `<dl>${Object.entries(value).map(([k,v])=>`<dt>${h.esc(k.replace(/_/g,' '))}</dt><dd>${explain(v)}</dd>`).join('')}</dl>`;
      const route=data.routes.find(r=>r.id===value);
      if(route)return `<button type="button" data-open-route="${h.esc(route.id)}">${h.esc(route.title)}</button>`;
      let text=String(value??'');for(const c of data.cards)text=text.replace(new RegExp(`\\b(?:CID\\s*)?${c.cid}\\b`,'g'),'');
      return h.text(text.replace(/ {2,}/g,' '));
    }
    const context=data.format_context;
    if(context && Object.values(context).some(value=>typeof value==='string'?value.trim()&&!/^PENDIENTE|^SIN_DATOS/i.test(value):Array.isArray(value)?value.length>0:Boolean(value&&typeof value==='object'&&Object.keys(value).length))){
      const skills=(data.skills||[]).length?h.strip(data.skills):'';
      content('intro').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Formato, condiciones y Skill</summary>${fields({'Mecánicas':context.format_and_mechanics,'Condiciones de este deck':context.deckSpecificControl,'Situaciones':context.criticalScenarios,'Reglas del formato':context.formatWarnings},h)}${skills}</details>`);
    }
    const dossier=data.delivery_dossier||{};
    const labels={A01_identidad:'Plan de juego',A02_cartas_proposito:'Piezas y propósito',A03_costes_limites:'Costes y límites',A04_extra_acceso:'Acceso al Extra',A05_main:'Funciones del Main',A06_sinergias:'Relaciones del motor',A07_interrupciones:'Ante interrupciones',A08_clasificacion:'Tipos de línea',A09_rutas:'Rutas para estudiar',A10_guia_visual:'Cómo seguir las jugadas'};
    const sections=Object.entries(dossier).filter(([k])=>labels[k]);
    if(sections.length)content('intro').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Cómo decidir con este deck</summary>${sections.map(([k,v])=>`<section><h4>${labels[k]}</h4>${explain(v)}</section>`).join('')}</details>`);
    if(data.side_plans?.length){
      content('plans').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Planes de Side · ${data.side_plans.length}</summary><p>Son alternativas para preparar otra partida. Ningún intercambio está aplicado; no se suman todas las propuestas ni se usa una misma copia dos veces.</p><div class="curated-scenarios">${data.side_plans.map(p=>`<article><h4>${h.esc(p.card)}</h4><p><b>Contra:</b> ${h.text(p.matchup)}</p>${h.strip([p.photo,...(p.leaving_photo?[p.leaving_photo]:[])])}${fields({'Entra':p.entering,'Sale':p.leaving||'Carta saliente aún sin elegir','Qué ganas y qué sacrificas':p.tactical_gain_and_tradeoff,'Momento y coste':p.activation_and_resource_note},h)}${p.outgoing_candidate?`<details><summary>Posible salida ? sin elegir</summary>${fields({'Carta candidata':p.outgoing_candidate.outgoing_name,'Motivo':p.outgoing_candidate.reason,'Qu? pierdes':p.outgoing_candidate.loss,'Requisitos':p.outgoing_candidate.prerequisites},h)}</details>`:''}${!p.exact_swap_confirmed_by_source?'<p>La salida del Extra requiere una elección: este plan no define todavía un intercambio completo.</p>':''}<details><summary>Antes de elegir el cambio</summary>${list(p.test_questions,h)}</details></article>`).join('')}</div></details>`);
    }
    if(data.matchup_plans?.length)content('plans').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Planes según el rival · ${data.matchup_plans.length}</summary><div class="curated-scenarios">${data.matchup_plans.map(p=>`<article><h4>${h.esc(p.rival)}</h4><p>${h.text(p.situacion)}</p><ol>${(p.orden_de_juego||[]).map(s=>`<li>${h.text(s)}</li>`).join('')}</ol>${p.entrada_propuesta?.length?`<p><b>Propuesta de entrada:</b> ${h.esc(p.entrada_propuesta.map(c=>`${data.cards.find(x=>x.cid===c.cid)?.name||'Carta sin identificar'} ×${c.copias} (${c.zona_origen})`).join('; '))}</p>`:''}${p.salida_propuesta?.length?`<p><b>Propuesta de salida:</b> ${h.esc(p.salida_propuesta.map(c=>`${data.cards.find(x=>x.cid===c.cid)?.name||'Carta sin identificar'} ×${c.copias} (${c.zona_origen})`).join('; '))}</p>`:''}${fields({'Qué pierdes con el cambio':p.criterio_corte,'Con la lista actual':p.si_no_hay_cambio},h)}<p>Las propuestas de cartas no están aplicadas.</p></article>`).join('')}</div></details>`);
    if(data.improvement_experiments?.length)content('practice').insertAdjacentHTML('beforeend',`<details class="delivery-details"><summary>Pruebas de mejora · ${data.improvement_experiments.length}</summary>${data.improvement_experiments.map(p=>`<article><h4>${h.esc(p.card)}</h4><p>Propuesta: ${h.esc(p.existing_quantity)} → ${h.esc(p.proposed_quantity)} copias. Sin aplicar.</p><p>${h.text(p.existing_rationale)}</p>${fields({'Pregunta':p.exercise?.question,'Método':p.exercise?.method,'Cuándo rechazarla':p.exercise?.failure_case},h)}</article>`).join('')}</details>`);
  }
  root.YgoDeliveryExtensions={card,causalCard,route,mount};
  if(typeof module==='object'&&module.exports)module.exports=root.YgoDeliveryExtensions;
})(typeof window!=='undefined'?window:globalThis);
