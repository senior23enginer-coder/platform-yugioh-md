(function(root,factory){
  if(typeof module==='object'&&module.exports)module.exports=factory();
  else root.YgoStepLesson=factory();
})(typeof globalThis!=='undefined'?globalThis:this,function(){
  'use strict';
  const text=v=>typeof v==='string'?v.trim():'',norm=v=>text(v).replace(/\s+/g,' ').toLowerCase();
  const paragraphs=v=>text(v).split(/\n\s*\n/).map(text).filter(Boolean);
  const unique=rows=>rows.filter((v,i)=>v&&!rows.slice(0,i).some(p=>norm(p).includes(norm(v))));
  function plain(value){
    return text(value).replace(/^CL(\d+)\s+(.+?)\s+en\s+Main\s+Phase:\s*(añadir|buscar|invocar|enviar|desterrar)\s+/i,
      (_,number,card,verb)=>`Durante la Fase Principal, activa el efecto de ${card} como CL${number} para ${verb.toLowerCase()} `)
      .replace(/\bGY\b/g,'Cementerio').replace(/\bCL(\d+)\b/g,'eslabón $1 de la Cadena (CL$1)')
      .replace(/^Invocar Normalmente\b/i,'Invoca de forma Normal').replace(/^Invocar Especialmente\b/i,'Invoca de forma Especial')
      .replace(/^Invocar\b/i,'Invoca').replace(/^Activar\b/i,'Activa').replace(/^Resolver\b/i,'Resuelve')
      .replace(/^Añadir\b/i,'Añade').replace(/^Enviar\b/i,'Envía').replace(/^Mandar\b/i,'Envía')
      .replace(/^Desterrar\b/i,'Destierra').replace(/^Superponer\b/i,'Superpón').replace(/^Colocar\b/i,'Coloca');
  }
  function sentences(value,names=[]){
    // Protect literal card names before separating sentences; F.A. and S:P.
    // must remain card names, not become extra instructions.
    const saved=[];let s=text(value);
    for(const name of names.filter(Boolean).sort((a,b)=>b.length-a.length)){
      const marker=`\uE000${saved.length}\uE001`;saved.push(name);s=s.split(name).join(marker);
    }
    s=s.replace(/\b[A-Z](?:[.:][A-Z]){1,3}\./g,name=>{const marker=`\uE000${saved.length}\uE001`;saved.push(name);return marker;});
    return s.split(/(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ¿¡])/u).map(line=>{
      for(let i=0;i<saved.length;i++)line=line.split(`\uE000${i}\uE001`).join(saved[i]);
      return plain(line);
    }).filter(Boolean);
  }
  const stateZone=/^(Mano|Monstruos|Mágicas y trampas|Magias y trampas|Cementerio|Destierro propio|Campo y estado rival|Materiales Xyz\/Fusión\/Sincro|Normal, Extra, fase y restricciones):\s*(.*)$/i;
  function fromNode(node,documented=false){
    const raw=text(node.explanation),parts=paragraphs(raw),roles=[],states=[],warnings=[],other=[];
    for(const p of parts){
      const lines=p.split('\n').map(text).filter(Boolean);
      if(documented&&lines.every(line=>stateZone.test(line))){
        for(const line of lines){const m=line.match(stateZone);if(m[2])(/^Normal, Extra/i.test(m[1])?warnings:states).push({label:m[1],value:m[2]});}
      }else if(documented&&lines.every(line=>line.includes(' · '))&&lines.every(line=>!/[.!?]\s/.test(line.slice(line.lastIndexOf(' · ')+3)))){
        roles.push(...lines.map(line=>{const at=line.lastIndexOf(' · ');return {name:line.slice(0,at),action:line.slice(at+3)};}));
      }else other.push(p);
    }
    const primary=other.shift()||'',additional=[],covered=other.filter(p=>norm(primary).includes(norm(p)));
    for(let p of other){
      if(norm(primary).includes(norm(p)))continue;
      // Imported visual prose repeats the action after the card/photo labels.
      // Remove only verbatim repetitions; retain all remaining instructions.
      if(documented&&primary&&p.includes(primary)){
        const at=p.indexOf(primary),prefix=p.slice(0,at);
        if(roles.length&&roles.some(role=>prefix.includes(role.name)))p=p.slice(at);
        p=p.replace(primary,'');
        for(const known of [primary,...covered,...additional])if(known)p=p.split(known).join('');
        p=p.replace(/\*?Recurso\s*\/\s*restricción:\*?/gi,'').trim();
      }
      if(p&&!additional.some(known=>norm(known).includes(norm(p))))additional.push(p);
    }
    const d=node.details||{};
    if(d.restrictions)warnings.push({label:'Restricciones',value:d.restrictions});
    if(d.remaining)states.push({label:'Recursos restantes',value:d.remaining});
    const instruction=primary||(node.kind==='opening'?'Prepara las cartas mostradas y comprueba las condiciones iniciales de la jugada.':'');
    return {kind:node.kind,documented,instruction,actions:sentences(instruction,roles.map(r=>r.name)),additional:unique(additional),roles,states,warnings,
      before:[d.cost&&{label:'Coste que pagas',value:d.cost},d.target&&{label:'Carta que seleccionas',value:d.target},d.timing&&{label:'Cuándo hacerlo',value:d.timing}].filter(Boolean),
      movement:d.origin||d.destination?{origin:d.origin,destination:d.destination}:null,original:raw};
  }
  function render(model,esc){
    const prose=(v,human=true)=>esc((model.documented?String(human?plain(v):v).replace(/\s*#\d{4,6}\b/g,''):human?plain(v):v)).replace(/\n/g,'<br>');
    const rows=(items,heading,cls)=>items.length?`<section class="step-lesson-${cls}"><h5>${heading}</h5><dl>${items.map(r=>`<dt>${esc(r.label)}</dt><dd>${prose(r.value)}</dd>`).join('')}</dl></section>`:'';
    const heading={opening:'Cómo preparar el inicio',result:'Qué obtienes en este punto',decision:'Elige la continuación'}[model.kind]||'Qué haces, en orden';
    const role=value=>({'ESPECIAL':'Carta que Invocas de forma Especial.','NORMAL':'Carta que Invocas de forma Normal.','AÑADIR A MANO':'Carta que añades a tu mano.','ACTIVAR':'Carta o efecto que activas.','MATERIAL':'Carta que utilizas como material.','EN MANO':'Debe estar en tu mano al comenzar.'}[text(value).toUpperCase()]||value);
    return `<div class="step-lesson">${rows(model.before,'Antes de hacer este paso','before')}${model.roles.length?rows(model.roles.map(r=>({label:r.name,value:role(r.action)})),'Función de las cartas mostradas','roles'):''}<section><h5>${heading}</h5>${model.actions.length?`<ol>${model.actions.map(action=>`<li>${prose(action,false)}</li>`).join('')}</ol>`:'<p>El autor todavía no ha explicado la acción de este paso.</p>'}</section>${model.movement?`<section class="step-lesson-move"><h5>Movimiento de la carta</h5><p>${prose(model.movement.origin||'Origen no indicado')} → ${prose(model.movement.destination||'Destino no indicado')}</p></section>`:''}${model.additional.length?`<section><h5>También ten en cuenta</h5>${model.additional.map(v=>`<p>${prose(v)}</p>`).join('')}</section>`:''}${rows(model.warnings,'Límites y restricciones que debes respetar','warnings')}${rows(model.states,'Así queda el estado indicado','state')}<details class="step-lesson-original"><summary>Consultar el texto completo del autor</summary><p>${esc(model.original).replace(/\n/g,'<br>')}</p></details></div>`;
  }
  function conditions(value,esc){
    const facts=[],sources=[];
    for(const p of paragraphs(value)){
      const lines=p.split('\n').map(text).filter(Boolean);
      if(lines.every(line=>/^https:\/\/[^\s<>]+$/.test(line)))sources.push(...lines);
      else facts.push(p);
    }
    return `<div class="cc-conditions step-lesson-conditions"><b>Comprueba esto antes de empezar</b>${facts.length?`<ul>${unique(facts).map(p=>`<li>${esc(plain(p)).replace(/\n/g,'<br>')}</li>`).join('')}</ul>`:''}${sources.length?`<details><summary>Fuentes de la jugada (${sources.length})</summary><ol>${[...new Set(sources)].map((url,i)=>`<li><a href="${esc(url)}" target="_blank" rel="noopener noreferrer">Consultar fuente ${i+1}</a></li>`).join('')}</ol></details>`:''}</div>`;
  }
  return {plain,sentences,fromNode,render,conditions};
});
