/* Persisted knockout rounds and a read-only projection of the existing mirrored pairing rule. */
window.StationXCupTree = (() => {
  function build(matches, competition, settings, decks, appearance = {}) {
    const rounds = new Map(), nodes = [], columns = [];
    for (const m of matches) {
      const r = Number(m.ronda || m.round_number || 1);
      if (!rounds.has(r)) rounds.set(r, []);
      rounds.get(r).push(m);
    }
    if (!rounds.size) return null;
    const first = Math.min(...rounds.keys());
    for (const list of rounds.values()) list.sort((a,b) => Number(a.mesa || a.table_number || a.bracket_slot || 0)-Number(b.mesa || b.table_number || b.bracket_slot || 0) || Number(a.id)-Number(b.id));
    const done = m => ['FINALIZADA','FINALIZADO','COMPLETED'].includes(String(m.estado || m.status).toUpperCase());
    const side = (m, key) => String(m[key] || 'Por definir');
    function matchNode(round, slot, actual, inputs = []) {
      const winner = actual && done(actual) && ['A','B'].includes(actual.result) ? actual.result : '';
      const n = {key:`r${round}m${slot}`,column:round-first,round,slot,actual,inputs,winner,
        sides:actual?[side(actual,'a'),side(actual,'b')]:inputs.map(t=>t.name || t.label),
        logos:actual?['a','b'].map(s=>appearance.logo?.(actual,s) || ''):inputs.map(t=>t.logo || '')};
      nodes.push(n);
      return {node:n,name:winner?side(actual,winner.toLowerCase()):'',logo:winner?n.logos[winner==='A'?0:1]:'',label:`Ganador R${round} · M${slot}`};
    }
    const initial = rounds.get(first);
    let outputs = initial.map((m,i) => matchNode(first,i+1,m));
    columns.push({round:first,label:initial[0].bracket_round_label || `Ronda ${first}`});
    const byeIds = (()=>{try{const ids=JSON.parse(settings[`cup_bye_${competition.id}_${first}`] || '[]');return Array.isArray(ids)?ids.map(Number).filter(id=>Number.isSafeInteger(id)&&id>0):[]}catch{return []}})();
    for (const id of byeIds) {
      const name = decks.find(d=>Number(d.id)===Number(id))?.name || `Deck #${id}`;
      const n={key:`r${first}bye${id}`,column:0,round:first,bye:true,inputs:[],sides:[name]};nodes.push(n);outputs.push({node:n,name,label:name});
    }
    let round=first;
    while (outputs.length>1 && round-first<12) {
      round++;
      const count=outputs.length, actual=rounds.get(round) || [], next=[];
      columns.push({round,label:actual[0]?.bracket_round_label || (count===2?'Final':count===4?'Semifinales':`Ronda ${round} · ${count} clasificados`)});
      for(let i=0;i<Math.floor(count/2);i++) next.push(matchNode(round,i+1,actual[i] || null,[outputs[i],outputs[count-1-i]]));
      if(count%2) {
        const input=outputs[Math.floor(count/2)];
        const n={key:`r${round}bye`,column:round-first,round,bye:true,inputs:[input],sides:[input.name || input.label],logos:[input.logo || '']};nodes.push(n);
        next.push({node:n,name:input.name,logo:input.logo,label:input.label});
      }
      outputs=next;
    }
    const champion={key:'champion',column:columns.length,champion:true,inputs:[outputs[0]],sides:[outputs[0]?.name || 'Por definir']};nodes.push(champion);
    let leaf=0;
    function position(n) {
      if(n.y!==undefined)return n.y;
      n.y=n.inputs.length?n.inputs.map(t=>position(t.node)).reduce((a,b)=>a+b,0)/n.inputs.length:76+(leaf++)*136;
      n.x=16+n.column*282;return n.y;
    }
    position(champion);
    return {nodes,columns,width:16+columns.length*282,height:Math.max(220,...nodes.map(n=>n.y+72))};
  }
  function render(matches, competition, settings, decks, esc, appearance = {}) {
    const tree=build(matches,competition,settings,decks,appearance);if(!tree)return '';
    const visible=tree.nodes.filter(n=>!n.champion);
    const links=visible.flatMap(n=>n.inputs.map(input=>{
      const s=input.node,x=s.x+250,end=n.x,middle=(x+end)/2;
      return `<path class="${input.name?'is-confirmed':''}" d="M ${x} ${s.y} H ${middle} V ${n.y} H ${end}"/>`;
    })).join('');
    const cards=visible.map(n=>{
      let content;
      if(n.bye) content=`<span class="cup-tree-status">Pase directo</span><strong>${esc(n.sides[0])}</strong><small>Avanza sin disputar un combate.</small>`;
      else {
        const m=n.actual,score=String(m?.score || '').split('-');
        const status=String(m?.estado || m?.status || '').toUpperCase();
        const finished=['FINALIZADA','FINALIZADO','COMPLETED'].includes(status), active=['EN CURSO','REQUIERE D5'].includes(status);
        const date=m?.scheduled_on?.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        const dateLabel=date?`${date[3]}/${date[2]}/${date[1].slice(-2)}`:'Por programar';
        const state=finished?'Fin':active?'En curso':m?'Pendiente':'Previsto';
        content=`<span class="cup-tree-meta"><time>${esc(dateLabel)}</time><span class="cup-tree-pill ${active?'is-live':''}">${state}</span></span>${n.sides.map((name,i)=>{
          const logo=n.logos?.[i];
          const image=logo?`<img src="${esc(logo)}" alt="" loading="lazy">`:`<span class="cup-tree-logo-fallback" aria-hidden="true">${esc(name.startsWith('Ganador ')?'?':name.slice(0,2).toUpperCase())}</span>`;
          return `<span class="cup-tree-side ${n.winner===['A','B'][i]?'is-winner':n.winner?'is-eliminated':''}">${image}<span class="cup-tree-side-name" title="${esc(name)}">${esc(name)}</span><b>${esc(m&&(finished||active)?score[i] || '0':'—')}</b></span>`;
        }).join('')}`;
      }
      const ready=!n.actual&&!n.bye&&n.inputs.length===2&&n.inputs.every(input=>Boolean(input.name));
      const tag=n.actual||ready?'button':'article';
      const preview=ready?{competition:competition.name,round:n.round,slot:n.slot,sides:n.sides,logos:n.logos}:null;
      const action=n.actual?` type="button" data-schedule-open-match="${esc(n.actual.id)}" aria-label="Ver ficha: ${esc(n.sides.join(' contra '))}, R${n.round} M${n.slot}"`:ready?` type="button" data-cup-preview="${esc(JSON.stringify(preview))}" aria-label="Abrir combate previsto: ${esc(n.sides.join(' contra '))}, R${n.round} M${n.slot}"`:'';
      return `<${tag}${action} class="cup-tree-node ${n.actual?'is-persisted':'is-projected'} ${n.bye?'is-bye':''}" data-cup-tree-node="${n.key}" title="Ronda ${n.round} · Mesa ${n.slot || 'Pase directo'}" style="left:${n.x}px;top:${n.y-56}px">${content}</${tag}>`;
    }).join('');
    const champion=tree.nodes.find(n=>n.champion)?.sides[0];
    return `<section class="cup-tree-shell"><div class="cup-tree-navigation"><button type="button" data-cup-tree-move="-1" aria-label="Ver rondas anteriores">‹</button><div class="cup-tree-label-window"><div class="cup-tree-labels" style="width:${tree.width}px">${tree.columns.map(c=>`<h3>${esc(c.label)}</h3>`).join('')}</div></div><button type="button" data-cup-tree-move="1" aria-label="Ver rondas siguientes">›</button></div><div class="cup-tree-scroll" aria-label="Árbol de eliminación de ${esc(competition.name)}" tabindex="0"><div class="cup-tree-canvas" style="width:${tree.width}px;height:${tree.height}px"><svg class="cup-tree-lines" width="${tree.width}" height="${tree.height}" aria-hidden="true">${links}</svg>${cards}</div></div><p class="cup-tree-help">Selecciona un combate para ver su ficha. Los cruces previstos se confirman con los ganadores.${champion&&champion!=='Por definir'?` Campeón: ${esc(champion)}.`:''}</p></section>`;
  }
  function mount(root) {
    root.querySelectorAll('.cup-tree-shell').forEach(shell=>{
      const scroll=shell.querySelector('.cup-tree-scroll'), labels=shell.querySelector('.cup-tree-labels');
      const back=shell.querySelector('[data-cup-tree-move="-1"]'), next=shell.querySelector('[data-cup-tree-move="1"]');
      shell.updateCupNavigation=()=>{
        labels.style.transform=`translateX(${-scroll.scrollLeft}px)`;
        back.disabled=scroll.scrollLeft<2;
        next.disabled=scroll.querySelector('.cup-tree-canvas').getBoundingClientRect().right<=scroll.getBoundingClientRect().right+2;
      };
      scroll.addEventListener('scroll',shell.updateCupNavigation,{passive:true});
      for(const button of [back,next])button.addEventListener('click',()=>scroll.scrollBy({left:Number(button.dataset.cupTreeMove)*282,behavior:'smooth'}));
      shell.querySelectorAll('.cup-tree-side img').forEach(img=>{
        const fallback=()=>{
          const badge=document.createElement('span');badge.className='cup-tree-logo-fallback';badge.setAttribute('aria-hidden','true');
          badge.textContent=(img.parentElement?.querySelector('.cup-tree-side-name')?.textContent || '?').slice(0,2).toUpperCase();
          img.replaceWith(badge);
        };
        img.addEventListener('error',fallback,{once:true});
        if(img.complete&&!img.naturalWidth)fallback();
      });
      shell.updateCupNavigation();
    });
  }
  document.addEventListener('shown.bs.modal',e=>e.target.querySelectorAll('.cup-tree-shell').forEach(s=>s.updateCupNavigation?.()));
  window.addEventListener('resize',()=>document.querySelectorAll('.cup-tree-shell').forEach(s=>s.updateCupNavigation?.()));
  return {build,render,mount};
})();
