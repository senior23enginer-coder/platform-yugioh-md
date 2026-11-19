(function(root){
  'use strict';
  const text=v=>typeof v==='string'?v:'',norm=v=>text(v).normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase().trim();
  const path=v=>text(v).replace(/^\.\//,'');
  function stableId(deck,id){let a=2166136261,b=5381;for(const ch of `${deck}:${id}`){a=Math.imul(a^ch.charCodeAt(0),16777619);b=Math.imul(b,33)^ch.charCodeAt(0);}return `documented-${deck}-${(a>>>0).toString(16).padStart(8,'0')}${(b>>>0).toString(16).padStart(8,'0')}`;}
  function chunks(s,size){let chars=Array.from(s||'');const out=[];while(chars.length){let cut=Math.min(size,chars.length);if(cut<chars.length){for(let i=cut;i>size/2;i--)if(/\s/.test(chars[i-1])){cut=i;break;}}out.push(chars.slice(0,cut).join('').trim());chars=chars.slice(cut);}return out.length?out:[''];}
  const short=(s,size)=>chunks(s,size)[0];
  function convert(data,route,cards,skills=[]){
    const deck=Number(data.deck_id),issues=[];
    if(cards.some(c=>Number(c.deck_id)!==deck))throw Error('La lista contiene cartas de otro deck.');
    if(route.native_editor_document){
      const doc=JSON.parse(JSON.stringify(route.native_editor_document));
      if(doc.id!==stableId(deck,route.id)||doc.deck_id!==deck||doc.source_route_id!==route.id||doc.nodes.some(n=>n.cards.some(id=>!cards.some(c=>c.id===id))))throw Error('La revisión del editor no corresponde al deck y sus cartas.');
      return {id:doc.id,deck_id:deck,title:doc.title,revision:0,document:doc,imported:true,source_route_id:route.id,stale:false,cards_snapshot:cards,issues:[]};
    }
    const doc={schema_version:1,id:stableId(deck,route.id),source_route_id:route.id,deck_id:deck,kind:'combo',title:route.title,objective:route.purpose||'',conditions:'',turn_order:route.turn_order||'any',board_format:data.format==='rush_duel'?'rush':data.format==='goat'?'goat':'tcg',editorial_status:route.editorial_status||(route.historical?'not_executable':'pending'),nodes:[],edges:[]};
    if(Array.from(doc.title||'').length>160)throw Error('El título documental supera el tamaño del editor.');
    const bind=(p,zone='')=>{
      const found=data.cards.filter(c=>(p.image&&path(c.image)===path(p.image))||(p.full_image&&c.full_image&&path(c.full_image)===path(p.full_image)));
      if(found.length!==1)return null;
      const slots=[...found[0].slots].sort((a,b)=>['main','extra','side','reserve'].indexOf(a.section)-['main','extra','side','reserve'].indexOf(b.section));
      return slots.map(s=>cards.find(c=>Number(c.id)===Number(s.card_id)&&c.section===s.section)).find(c=>c&&(zone!=='hand'||c.section==='main'))||null;
    };
    const link=(a,b,label='Continuar')=>{if(a&&b)doc.edges.push({from:a.id,to:b.id,label:short(label,160)});};
    const add=(kind,title,body='',refs=[],placements=[],action='')=>{
      const parts=chunks(body,4000),nodes=[],groups=[];
      for(let i=0;i<refs.length;i+=8)groups.push(refs.slice(i,i+8));
      while(parts.length<groups.length)parts.push('Continuación de las cartas mencionadas en el mismo paso; no es una acción adicional.');
      for(let i=0;i<parts.length;i++){
        const n=doc.nodes.length;if(n>=60)throw Error('Esta guía supera los 60 nodos del editor.');
        const node={id:`node-${n+1}`,kind:i?'step':kind,title:short(i?`${title} · detalle ${i+1}`:title,120),action:short(i?'Continuación del mismo detalle':action,80),explanation:parts[i].trim(),cards:groups[i]||[],x:40+(n%4)*320,y:40+Math.floor(n/4)*340};
        const ownPlacements=placements.filter(p=>p.card_index>=i*8&&p.card_index<(i+1)*8).map(p=>({...p,card_index:p.card_index-i*8}));
        if(ownPlacements.length)node.placements=ownPlacements;
        doc.nodes.push(node);link(nodes.at(-1),node);nodes.push(node);
      }
      return {first:nodes[0],last:nodes.at(-1)};
    };
    const photoRefs=(photos,opening=false)=>{
      const refs=[];for(const p of photos||[]){const card=bind(p,opening?'hand':'');if(card){if(!refs.includes(card.id))refs.push(card.id);}else if(p.name)issues.push({name:p.name,reason:'Referencia sin vínculo nativo; se conserva en el texto.'});}
      return refs;
    };
    // Algunas fuentes anteriores incluyen acciones completas pero no imágenes por paso.
    // Vincular solo nombres exactos del catálogo editorial a cartas del propio deck;
    // son referencias visuales, nunca ubicaciones ni movimientos inferidos.
    const stepRefs=(step)=>{
      const explicit=photoRefs(step.photos),seen=new Set(explicit);
      const narration=norm([step.action,step.detail,step.visual_detail].filter(Boolean).join(' '));
      const candidates=(data.cards||[]).filter(c=>c.name&&norm(c.name).length>=4)
        .map(c=>({card:c,names:[norm(c.name),...(
          deck===671?({
            'tatsunootoshigo':['tatsun'],
            'demoniherrero grabador':['grabador'],
            'requiem del demoniherrero':['requiem'],
            's:p pequena caballera':['s:p']
          }[norm(c.name)]||[]):[]
        )]}))
        .map(({card,names})=>({card,offset:Math.min(...names.map(name=>{
          let at=narration.indexOf(name);
          while(at>=0){
            const left=narration[at-1]||' ',right=narration[at+name.length]||' ';
            if(!/[a-z0-9]/.test(left)&&!/[a-z0-9]/.test(right))return at;
            at=narration.indexOf(name,at+1);
          }
          return Infinity;
        }))}))
        .filter(({offset})=>Number.isFinite(offset))
        .sort((a,b)=>a.offset-b.offset);
      for(const {card} of candidates){
        const bound=bind({image:card.image,full_image:card.full_image});
        if(bound&&!seen.has(bound.id)){seen.add(bound.id);explicit.push(bound.id);}
      }
      return explicit;
    };
    const names=photos=>(photos||[]).map(p=>[p.name,p.label].filter(Boolean).join(' · ')).join('\n');
    const resources=rows=>(rows||[]).map(r=>`${r.zone}: ${r.text}`).join('\n');
    const requirements=[route.opening,route.initial_details,route.restriction,route.notice,...(route.final_constraints||[]),route.next_phase,...(route.sources||[]),route.historical?'Condición de formato: esta secuencia requiere cartas que el formato indicado no permite; se conserva completa para consulta y edición.':''].filter(Boolean).join('\n\n');
    const requirementParts=chunks(requirements,6000);doc.conditions=requirementParts.shift().trim();
    const isHand=route.initial_label==='Mano inicial'&&(route.hand_photos||[]).every(p=>bind(p,'hand'));
    const openingRefs=photoRefs(route.hand_photos,isHand),opening=add(isHand?'opening':'step',route.initial_label||'Estado inicial',names(route.hand_photos),openingRefs,isHand?openingRefs.map((id,i)=>({card_index:i,zone:'hand',position:'unknown',materials:''})):[],isHand?'Preparar mano':'Revisar estado inicial');let last=opening.last;
    for(const part of requirementParts){const n=add('step','Requisitos · continuación',part);link(last,n.first);last=n.last;}
    for(const [i,s]of route.steps.entries()){
      const body=[...new Set([s.action,s.detail,s.visual_detail].filter(Boolean)),names(s.photos),...(s.requirements||[]),resources(s.resources)].filter(Boolean).join('\n\n');
      const n=add('step',`Paso ${i+1}`,body,stepRefs(s),[],s.photos?.[0]?.label||'Acción documentada');link(last,n.first);last=n.last;
    }
    if(route.technical){const n=add('step','Notas y alternativas de la fuente',route.technical);link(last,n.first);last=n.last;}
    const variants=route.endboard?.variants||[];
    if(variants.length>1){const n=add('decision','Elige el resultado que corresponde','Las alternativas son excluyentes. Sigue únicamente la rama cuyas condiciones se cumplan.');link(last,n.first);last=n.last;}
    const branchFrom=last;
    const zoneNames={monster:'Monstruos',spell:'Magias y trampas',field:'Campo',hand:'Mano',graveyard:'Cementerio',banished:'Destierro',deck:'Deck',extra:'Extra boca arriba'};
    for(const v of variants.length?variants:[{label:'Resultado documentado'}]){
      const entries=[],notes=[];
      for(const [key,label]of Object.entries(zoneNames))for(const p of v[key]||[]){
        const line=[`${label}: ${p.name||'Carta sin identificar'}`,p.quantity===null?'cantidad no determinada':`×${p.quantity||1}`,p.copy_label,p.position,p.statistic,p.note,p.equipped_to?`Equipado a ${p.equipped_to}`:'',p.conditional?'Presencia condicionada':'',p.materials?.length?'Materiales: '+p.materials.map(m=>`${m.name} ×${m.quantity||1}`).join(' + '):p.material_count!==undefined?`${p.material_count} materiales; identidades sin especificar`:''].filter(Boolean).join(' · ');notes.push(line);
        if(p.conditional||p.quantity===null)continue;
        const card=bind(p,key);if(!card){if(p.name)issues.push({name:p.name,reason:'Estado sin carta vinculada; conservado en el resultado.'});continue;}
        const qty=p.quantity||1;if(qty>Number(card.quantity))throw Error(`La lista actual no contiene las ${qty} copias documentadas de ${p.name}.`);
        const zone=key==='monster'?(p.row==='extra_monster'?`extra_${p.slot}`:`monster_${p.slot}`):key==='spell'?`spell_${p.slot}`:key==='graveyard'?'gy':key;
        const position=/boca abajo|colocad/i.test(p.position||'')?'face_down':/defensa/i.test(p.position||'')?'defense':/ataque/i.test(p.position||'')?'attack':'unknown';
        for(let copy=0;copy<qty;copy++)entries.push({id:card.id,placement:{zone,position,materials:p.materials?.length?p.materials.map(m=>`${m.name} ×${m.quantity||1}`).join(' + '):p.material_count!==undefined?`${p.material_count} materiales; identidades sin especificar`:''}});
      }
      const body=[v.label,route.result,resources(route.resources),v.layout_note,v.note,v.opponent,...(v.opponent_objects||[]).map(p=>p.name),...notes,...(v.notes||[]),...Object.entries(v.zone_notes||{}).map(([k,t])=>`${zoneNames[k]||k}: ${t}`),...(v.skill||[]).map(p=>`Skill externa: ${p.name}`)].filter(Boolean).join('\n\n');
      const batches=[];for(let i=0;i<entries.length;i+=8)batches.push(entries.slice(i,i+8));if(!batches.length)batches.push([]);
      let previous=branchFrom;
      for(let i=0;i<batches.length;i++){
        const batch=batches[i],refs=batch.length?batch.map(p=>p.id):(!variants.length?photoRefs(route.board):[]),placements=batch.map((p,j)=>({card_index:j,...p.placement}));
        const n=add('result',`${v.label||'Campo final'}${batches.length>1?` · parte ${i+1}/${batches.length}`:''}`,i?'Continuación del mismo estado final. Las partes describen recursos simultáneos, no acciones nuevas.':body,refs,placements,'Resultado documentado');link(previous,n.first,previous===branchFrom?v.label||'Campo final':'Otros recursos del mismo resultado');previous=n.last;
      }
    }
    const skill=skills.find(s=>(data.skills||[]).some(p=>norm(p.name)===norm(s.name)));
    if(skill&&doc.board_format==='rush'){doc.skill_id=skill.id;doc.skill_snapshot=skill;}
    return {id:doc.id,deck_id:deck,title:doc.title,revision:0,document:doc,imported:true,source_route_id:route.id,stale:false,cards_snapshot:cards,issues};
  }
  async function load(deckId,cards,skills=[]){
    const read=async url=>{const r=await fetch(url,{cache:'no-store'});if(!r.ok)throw Error('No se pudieron cargar los combos documentados.');return r.json();};
    const state=await read('./assets/editorial/strategy-content-state.json?v=20261113-editor-import-168'),entry=state.checked_studies?.[String(deckId)];
    if(!entry||entry.presentation!=='curated-v62')return {records:[],issues:[]};
    const data=await read(entry.path);if(Number(data.deck_id)!==Number(deckId))throw Error('La documentación corresponde a otro deck.');
    const records=[],issues=[];
    for(const r of data.routes){try{records.push(convert(data,r,cards,skills));}catch(error){issues.push({route:r.title,reason:error.message});}}
    return {records,issues};
  }
  root.YgoDocumentedCombos={convert,load,stableId};
  if(typeof module==='object'&&module.exports)module.exports=root.YgoDocumentedCombos;
})(typeof window!=='undefined'?window:globalThis);
