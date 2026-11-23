(function(root){
  'use strict';
  const clean=value=>String(value||'').replace(/\bstarter\b/gi,'pieza de inicio').replace(/\bextensor\b/gi,'pieza para continuar la jugada').replace(/\bsearcher\b/gi,'buscador').replace(/\bremoval\b/gi,'retirada de cartas').replace(/\bhandtrap\b/gi,'interrupci\u00f3n desde la mano').replace(/\bdesde mano\b/gi,'desde la mano').replace(/\ben mano\b/gi,'en la mano').replace(/\bdesde Deck\b/gi,'desde el Deck').replace(/a\u00f1ade Kashtira monstruo/g,'a\u00f1ade un monstruo Kashtira').replace(/o rival activa efecto monstruo/g,'o el rival activa un efecto de monstruo').replace(/posibilita Normal/g,'permite la Invocaci\u00f3n Normal').replace(/\bGY\b/g,'Cementerio').replace(/\bCL(\d+)\b/g,'eslabón $1 de la Cadena').replace(/\bMain Phase\b/g,'fase principal').replace(/\ben Main\b/g,'en fase principal').replace(/\bKash\b/g,'Kashtira').replace(/\bNormal libre\b/g,'Invocación Normal disponible').replace(/\*\*|\*/g,'').trim();
  const normalize=s=>String(s||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toUpperCase();
  function role(label){
    const s=normalize(label);
    if(/^XYZ.*MATERIAL/.test(s))return {name:'Monstruo Xyz con materiales indicados',active:false};
    if(/^MATERIAL|COSTE|SACRIFICIO/.test(s))return {name:'Material o coste de la jugada',active:true};
    if(/OBJETIVO/.test(s))return {name:'Objetivo del efecto',active:true};
    if(/ESTADO|CONTEXTO|PERMANECE|YA EN CAMPO|^CAMPO$|RESULTADO/.test(s))return {name:'Presente en el estado del paso',active:false};
    if(/BUSC/.test(s))return {name:'Interviene en una búsqueda',active:true};
    if(/ANAD|A MANO/.test(s))return {name:'Carta añadida a la mano',active:true};
    if(/INVOCAR XYZ|INVOCAR SINCRO|FUSION|ENLAZ/.test(s))return {name:'Participa en una Invocación',active:true};
    if(/ESPECIAL/.test(s))return {name:'Invocación Especial',active:true};
    if(/NORMAL/.test(s))return {name:'Invocación Normal',active:true};
    if(/ACTIV/.test(s))return {name:'Activación del efecto',active:true};
    if(/DESTIERR|DESCART|COSTE|ENVI/.test(s))return {name:'Movimiento o consumo del recurso',active:true};
    if(/COLOCAR/.test(s))return {name:'Colocación en el campo',active:true};
    if(/EQUIPAR/.test(s))return {name:'Participa en un equipamiento',active:true};
    if(/REVELAR/.test(s))return {name:'Carta revelada',active:true};
    return {name:'Carta referenciada en el paso',active:false};
  }
  function participation(c,data){
    const matches=p=>p.cid!=null?Number(p.cid)===Number(c.cid):[c.image,c.full_image].filter(Boolean).some(src=>String(src).replace(/^\.\//,'')===String(p.image||p.full_image).replace(/^\.\//,''));
    const result=[];
    for(const r of data.routes){
      let steps=[],opening=false,final=false,native=false;
      if(r.native_editor_document){
        native=true;const ids=new Set(c.slots.map(s=>Number(s.card_id))),doc=r.native_editor_document;
        const nodes=root.YgoComboModel?.ordered?root.YgoComboModel.ordered(doc):doc.nodes;
        let index=0;
        for(const n of nodes){const uses=(n.cards||[]).some(id=>ids.has(Number(id)));if(n.kind==='opening'){opening=opening||uses;continue;}if(n.kind==='result'){final=final||uses;continue;}if(uses)steps.push({index,number:index+1,node:n.id,action:clean(n.action||n.title),detail:clean(n.explanation),role:'Participación en paso editado',active:true});index++;}
      }else{
        steps=(r.steps||[]).flatMap((s,index)=>{const photos=(s.photos||[]).filter(matches);if(!photos.length)return [];const roles=photos.map(p=>role(p.label));return [{index,number:index+1,action:clean(s.compact_action||s.action),detail:clean(s.visual_detail||s.detail),role:[...new Set(roles.map(p=>p.name))].join(' · '),active:roles.some(p=>p.active)}];});
        opening=(r.hand_photos||[]).some(matches);final=(r.board||[]).some(matches);
      }
      const legacy=!native&&(c.route_links||[]).find(l=>l.id===r.id);
      if(steps.length||opening||final||legacy)result.push({id:r.id,title:r.title,purpose:clean(r.purpose||r.family),opening,final,native,steps,referenceOnly:!steps.length&&!opening&&!final,first:(steps.find(s=>s.active)||steps[0])?.index??0});
    }
    return result.sort((a,b)=>Number(b.steps.some(s=>s.active))-Number(a.steps.some(s=>s.active)));
  }
  function render(c,data,h){
    const {esc,text,strip}=h,field=Object.fromEntries((c.fields||[]).map(f=>[f.title,f.text]));
    const action=clean(c.operational?.['Acción']||field['Efecto']||c.role),first=clean(c.learning?.['Cuándo jugarla primero']||field['Al jugar primero']),second=clean(c.learning?.['Cuándo jugarla segundo']||field['Al jugar segundo']);
    const use=participation(c,data),active=use.filter(r=>r.steps.some(s=>s.active)),references=use.filter(r=>!r.steps.some(s=>s.active));
    const lines=value=>`<ul>${[...new Set(clean(value).split(/\s*;\s*|\n+|(?<=[.!?])\s+(?=[A-Z\u00c1\u00c9\u00cd\u00d3\u00da\u00d1])/u).filter(Boolean))].map(v=>`<li>${text(v.charAt(0).toLocaleUpperCase('es')+v.slice(1))}</li>`).join('')}</ul>`;
    const slotNames={main:'Main',extra:'Extra',side:'Side',reserve:'Reserva'};
    const relations=(data.causal_relations||[]).filter(r=>r.origen.cid===c.cid||r.destino.cid===c.cid);
    const connections=relations.length?`<section><h4>Con otras cartas de tu deck</h4>${relations.slice(0,2).map(r=>`<article><h5>${esc(r.origen.nombre)} \u2192 ${esc(r.destino.nombre)}</h5><p>${text(clean(r.interaccion))}</p>${[r.origen,r.destino].filter(p=>p.cid!==c.cid).map(p=>{const other=data.cards.find(x=>x.cid===p.cid);return other?`<button type="button" data-curated-card="${esc(other.id)}">Ver ficha de ${esc(other.name)}</button>`:'';}).join('')}</article>`).join('')}${relations.length>2?`<p>Hay ${relations.length-2} relaciones adicionales en la ficha completa.</p>`:''}</section>`:'';
    const group=(rows,title)=>rows.length?`<section class="card-insight-participation"><h4>${title} · ${rows.length}</h4>${rows.map(r=>`<article data-card-route="${esc(r.id)}"><h5>${esc(r.title)}</h5><p><b>Objetivo de la ruta:</b> ${text(r.purpose)}</p><p>${r.opening?'Forma parte de la mano inicial documentada. ':''}${r.final?'Figura en el estado final documentado. ':''}${r.referenceOnly?'Referencia heredada: no hay un paso específico identificado para esta carta.':''}</p>${r.steps.length?`<p><b>Papel de ${esc(c.name)}:</b></p><div class="card-insight-step-links">${r.steps.map(s=>`<button type="button" data-open-route="${esc(r.id)}" data-route-step="${s.index}" data-route-card="${esc(c.id)}"${s.node?` data-route-node="${esc(s.node)}"`:''}>Paso ${s.number} · ${esc(s.role)}<small>${text(s.action)}</small></button>`).join('')}</div>`:`<button type="button" data-open-route="${esc(r.id)}" data-route-card="${esc(c.id)}">Ver contexto y requisitos de la ruta</button>`}<details><summary>Leer las acciones y condiciones de sus pasos</summary>${r.steps.map(s=>`<h5>Paso ${s.number} · ${esc(s.role)}</h5><p>${text(s.detail||s.action)}</p>`).join('')||'<p>No hay una acción concreta atribuida a esta carta.</p>'}</details></article>`).join('')}</section>`:'';
    const extraAccess=Array.isArray(c.extra_access)?c.extra_access:[];
    const accessGroup=extraAccess.length?`<section data-extra-access><h4>Accesos adicionales al Extra Deck · ${extraAccess.length}</h4><p>Accesos documentales y condicionales: no se cuentan como nuevas rutas A09 ni como jugadas ejecutadas por el simulador.</p>${extraAccess.map(a=>`<article data-extra-access-id="${esc(a.id)}"><h5>${esc(a.id)} · ${esc(a.status==="TEORICA_PENDIENTE_VALIDACION"?"Hipótesis pendiente de verificación":"Acceso documentado con condiciones")}</h5><p><b>Desde qué estado:</b> ${text(a.opening)}</p><p><b>Materiales:</b> ${text(a.materials)}</p><ol>${(a.steps||[]).map(step=>`<li>${text(step)}</li>`).join("")}</ol><p><b>Resultado:</b> ${text(a.result)}</p><p><b>Límite y riesgos:</b> ${text(a.limitation)}</p><p>Referencia documental: ${esc(a.source_section||"A09")}. Este acceso todavía no tiene una secuencia interactiva A09 propia.</p></article>`).join("")}</section>`:"";
    return `<div class="curated-card-summary card-insight-summary">${strip([c])}<div><p>${c.slots.map(s=>`${slotNames[s.section]} ×${s.quantity}`).join(' · ')} · CID ${c.cid}</p><div class="curated-badges">${(c.synergy.labels||[]).map(s=>`<span>${esc(s)}</span>`).join('')}</div><section><h4>Para qué sirve en tu deck</h4><p>${text(clean(c.role))}</p>${c.connection?`<p>${text(c.connection.summary)}</p>`:''}</section><section class="curated-practical"><h4>Qué hace, explicado por acciones</h4>${lines(action)}</section><section><h4>Cómo usarla según tu turno</h4><div class="card-insight-plans"><article><h5>Si empiezas primero</h5>${lines(first||'No hay un plan específico documentado para esta situación.')}</article><article><h5>Si juegas segundo</h5>${lines(second||'No hay un plan específico documentado para esta situación.')}</article></div></section><section><h4>Comprueba esto antes de usarla</h4>${Object.entries({'Condiciones y momento':field['Ventanas y requisitos']||c.operational?.['Ventanas y requisitos']||field['Materiales y acceso al Extra (V40)'],'Coste que debes pagar':field['Coste']||c.operational?.['Coste'],'Restricciones y límites':field['Restricciones']||c.operational?.['Restricciones'],'Si el rival responde':field['Ante interrupciones']}).filter(([,v])=>v).map(([k,v])=>`<h5>${k}</h5><p>${text(clean(v))}</p>`).join('')||'<p>El paquete aún no aporta condiciones verificables para esta carta.</p>'}${c.connection?.availability?`<p>${text(c.connection.availability)}</p>`:''}</section>${connections}</div></div><section><h4>En qué jugadas participa</h4><p>Se distinguen acciones de la carta, uso como material u objetivo y presencia en un estado. Una imagen en el tablero no demuestra que su efecto haya sido usado.</p>${use.length?`${group(active,'Usos en la secuencia')}${group(references,'Recursos, estados y otras referencias')}`:''}${accessGroup}${!use.length&&!extraAccess.length?'<p>No hay una jugada enlazada con evidencia específica de esta carta. Su utilidad individual se muestra arriba.</p>':''}</section><details data-insight-sources><summary>Fuentes oficiales</summary>${c.sources.map(url=>`<p><a href="${esc(url)}" target="_blank" rel="noopener noreferrer">Consultar la fuente de ${esc(c.name)}</a></p>`).join('')}</details>`;
  }
  root.YgoCardDetail={clean,role,participation,render};
  if(typeof module==='object'&&module.exports)module.exports=root.YgoCardDetail;
})(typeof window!=='undefined'?window:globalThis);
