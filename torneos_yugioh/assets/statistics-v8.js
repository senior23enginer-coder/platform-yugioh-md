(function(root){
  'use strict';
  const escape = v => String(v ?? '').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const num = v => v == null ? 'N/D' : typeof v==='number'?v.toLocaleString('es-CO',{maximumFractionDigits:2}):escape(v);
  const source = () => root.DML_DATA || {};
  const name = (id,type='team') => {
    const rows=type==='team'?source().teams:source().allDecks;
    return (rows||[]).find(r=>Number(r.id)===Number(id))?.name || `${type==='team'?'Equipo':'Deck'} ${id}`;
  };
  function table(rows,columns){
    if(!rows.length)return '<p class="note">Pendiente: no hay evidencia suficiente para este alcance.</p>';
    return `<div class="stats-v8-table"><table class="table"><thead><tr>${columns.map(c=>`<th>${escape(c[0])}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${columns.map(c=>`<td>${c[1](r)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
  }
  const statusLabel = value => ({DESBLOQUEADO:'Obtenido',BLOQUEADO:'Por conseguir','REVISION PENDIENTE':'Faltan datos'})[value] || value;
  const rarityLabel = value => ({common:'Común',silver:'Plata',gold:'Oro',legendary:'Legendario',secret:'Secreto',platinum:'Platino',bronze:'Bronce',epic:'Épico',rare:'Raro'})[value] || value || '';
  function requirement(r){
    if(r.name==='Logro secreto'||r.target==null)return 'El requisito se revela al desbloquear este logro.';
    const units={match_wins:'partidas',duel_wins:'duelos',rivals_beaten:'rivales diferentes',perfect_matches:'partidas sin perder ningún duelo',flawless_wins:'duelos sin perder puntos de vida',best_streak:'partidas consecutivas',d5_wins:'partidas con victoria en D5',brick_wins:'duelos con mano inicial brick',comebacks:'partidas después de perder D1',reverse_sweeps:'partidas después de ir perdiendo 0-2',second_wins:'duelos empezando segundo',surrender_wins:'duelos por rendición rival',time_wins:'duelos por tiempo',under_10:'duelos en menos de 10 minutos',under_5:'duelos en menos de 5 minutos',under_5_turns:'duelos en 5 turnos o menos',under_3_turns:'duelos en 3 turnos o menos',deck_out_wins:'duelos por agotamiento del deck rival',effect_wins:'duelos por efecto de carta',dice_wins:'tiradas de dados',damage_dealt:'puntos de daño',events_won:'eventos distintos',formats_won:'formatos distintos',tier_zero_wins:'partidas contra Tier 0',tiers_beaten:'tiers diferentes'};
    if(r.unit==='no_surrender_season')return 'Completa una temporada con al menos 5 duelos ganados y sin perder por rendición; se necesita el registro completo de cierres.';
    if(r.unit==='undefeated_champion')return 'Consigue un campeonato confirmado sin perder partidas en ese evento.';
    if(r.unit==='damage_dealt')return `Acumula ${num(r.target)} puntos de daño con registro de puntos de vida.`;
    if(r.unit==='rivals_beaten')return `Vence a ${num(r.target)} rivales diferentes en partidas confirmadas.`;
    if(r.unit==='events_won')return `Consigue victorias de partidas en ${num(r.target)} eventos distintos.`;
    if(r.unit==='formats_won')return `Consigue victorias en ${num(r.target)} formatos distintos.`;
    if(r.unit==='tiers_beaten')return `Consigue victorias contra ${num(r.target)} tiers diferentes.`;
    return `Gana ${num(r.target)} ${units[r.unit]||'veces según la condición del logro'}${r.code==='format_victory'?' en este formato':''}.`;
  }
  function achievementRows(deck){
    const data=source().statisticsV8||{},own=(data.global||[]).filter(r=>deck.team_id&&Number(r.team_id)===Number(deck.team_id));
    const fixed=(own[0]?.achievements||data.achievement_catalog||[]).filter(a=>a.code!=='format_victory');
    return {own,fixed,dynamic:own.flatMap(r=>(r.achievements||[]).filter(a=>a.code==='format_victory'))};
  }
  function achievements(rows){
    const cards=items=>`<div class="stats-v8-achievements">${items.map(r=>{
      const unlocked=r.status==='DESBLOQUEADO',known=r.progress!=null&&r.target!=null&&Number(r.target)>0,percent=known?Math.min(100,Math.max(0,100*Number(r.progress)/Number(r.target))):0;
      return `<article class="stats-v8-achievement-card" data-achievement-state="${unlocked?'unlocked':'locked'}"><div class="stats-v8-award-heading"><i class="bi ${unlocked?'bi-award-fill':'bi-lock'}" aria-hidden="true"></i><h5>${escape(r.name)}</h5></div><span class="stats-v8-award-status ${unlocked?'is-earned':''}">${escape(statusLabel(r.status))}</span><p class="stats-v8-requirement">${requirement(r)}</p><div class="stats-v8-award-progress">${known?`<span>${num(r.progress)} / ${num(r.target)}</span><div class="stats-v8-progress-track" role="progressbar" aria-label="Progreso: ${escape(r.name)}" aria-valuemin="0" aria-valuemax="${escape(r.target)}" aria-valuenow="${Math.min(Number(r.target),Math.max(0,Number(r.progress)))}"><div style="width:${percent}%"></div></div>`:'<span>Progreso sin datos suficientes</span>'}</div><small>${escape(rarityLabel(r.rarity))} · Equipo · ${r.code==='format_victory'?'Por formato':'Historial elegible'}</small></article>`;
    }).join('')}</div>`;
    if(!rows.length)return '<p class="note">No hay logros para este alcance todavía.</p>';
    const groups=[...new Set(rows.map(r=>r.category||'Por formato'))];return groups.length>1?groups.map((category,i)=>`<details class="stats-v8-award-group" ${i===0?'open':''}><summary>${escape(category)} · ${rows.filter(r=>(r.category||'Por formato')===category).length} logros</summary>${cards(rows.filter(r=>(r.category||'Por formato')===category))}</details>`).join(''):cards(rows);
  }
  function medals(rows){return table(rows,[['Campeonato',r=>`${r.visual_asset_reference?`<img src="${escape(r.visual_asset_reference)}" alt="" width="32" height="32" style="object-fit:contain">`:'<i class="bi bi-trophy-fill"></i>'} ${escape(r.name)}`],['Formato',r=>escape(r.format_id||'No confirmado')],['Campeón',r=>r.team_id?escape(name(r.team_id)):'Pendiente de entrega'],['Bono',r=>num(r.status==='ENTREGADA'?r.bonus_points:0)],['Estado',r=>escape(r.status)]]);}
  function medalsForDeck(deck){
    const formats=new Set((deck.format_codes||[]).map(code=>String(code).trim().toLowerCase()).filter(Boolean));
    return (source().statisticsV8?.medals||[]).filter(m=>formats.has(String(m.format_id||'').trim().toLowerCase()));
  }
  function renderProfile(node,deck){
    const data=source().statisticsV8||{}, {own,fixed,dynamic}=achievementRows(deck),earned=medalsForDeck(deck).filter(m=>deck.team_id&&Number(m.team_id)===Number(deck.team_id)&&m.status==='ENTREGADA');
    node.innerHTML=`<div class="stats-v8-awards-toolbar"><p>${fixed.filter(r=>r.status==='DESBLOQUEADO').length} de ${fixed.length} logros obtenidos · ${earned.length} medallas de campeonato</p><button type="button" class="btn btn-outline-info" data-open-medal-catalog="${Number(deck.id)}"><i class="bi bi-award" aria-hidden="true"></i> Ver medallas disponibles</button></div><h4>Medallas obtenidas</h4>`+(earned.length?medals(earned):'<p class="note">Este equipo todavía no ha obtenido medallas de campeonato. Consulta las disponibles y sus requisitos en el catálogo.</p>')+'<h4>Logros y progreso</h4>'+achievements(fixed)+(dynamic.length?'<h4>Logros por formato</h4>'+achievements(dynamic):'');
    if(!own.length)node.insertAdjacentHTML('beforeend','<p class="note">El progreso se actualizará con los resultados confirmados de este equipo.</p>');
    const ffa=(data.free_for_all?.ranking||[]).filter(r=>Number(r.deck_id)===Number(deck.id));
    if(ffa.length)node.insertAdjacentHTML('beforeend','<h4>Elo Battle Royale</h4>'+table(ffa,[['Formato',r=>escape(r.format_id)],['Elo FFA',r=>num(r.rating)],['Mesas',r=>num(r.J)],['Victorias',r=>num(r.V)],['Estado',r=>escape(r.state)]]));
    if(own.length)node.insertAdjacentHTML('beforeend','<h4>Ranking del equipo</h4>'+table(own,[['Formato',r=>escape(r.format_id)],['Rating por partidas',r=>num(r.rating)],['Bonos de medallas',r=>num(r.bonus_medallas)],['Total global',r=>num(r.total)],['Estado',r=>escape(r.state)],['Muestra',r=>num(r.J)]]));
  }
  function openMedalCatalog(deck,trigger){
    if(!root.bootstrap)return;
    let modal=document.getElementById('deckMedalCatalogModal');
    if(!modal){document.body.insertAdjacentHTML('beforeend',`<div class="modal fade dml-modal stats-v8-catalog-modal" id="deckMedalCatalogModal" tabindex="-1" aria-labelledby="deckMedalCatalogTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title" id="deckMedalCatalogTitle">Medallas y logros disponibles</h2><p data-medal-catalog-context></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><div class="stats-v8-catalog-filters"><label>Buscar<input class="form-control" type="search" data-medal-search placeholder="Nombre, campeonato o requisito"></label><label>Mostrar<select class="form-select" data-medal-kind><option value="all">Medallas y logros</option><option value="medals">Medallas de campeonato</option><option value="achievements">Logros estadísticos</option></select></label></div><p class="note">Las medallas de campeonato se entregan con el título confirmado y aportan el bono indicado. Los logros estadísticos muestran el progreso del equipo y no suman ese bono.</p><p data-medal-catalog-count role="status"></p><div data-medal-catalog-results></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-info" data-bs-dismiss="modal">Volver a logros</button></div></div></div></div>`);modal=document.getElementById('deckMedalCatalogModal');}
    const {fixed,dynamic}=achievementRows(deck),data=source(),list=medalsForDeck(deck),search=modal.querySelector('[data-medal-search]'),kind=modal.querySelector('[data-medal-kind]'),results=modal.querySelector('[data-medal-catalog-results]');
    modal.querySelector('[data-medal-catalog-context]').textContent=`${deck.name} · ${deck.team||'Sin equipo asociado'}`;search.value='';kind.value='all';
    const normalize=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
    function render(){
      const term=normalize(search.value),matches=text=>normalize(text).includes(term);
      const ms=list.filter(m=>matches(m.name+' '+m.format_id+' Gana el campeonato '+m.bonus_points));
      const as=[...fixed,...dynamic].filter(r=>matches(r.name+' '+r.category+' '+requirement(r)));
      const showM=kind.value!=='achievements',showA=kind.value!=='medals';modal.querySelector('[data-medal-catalog-count]').textContent=`${showM?ms.length:0} medallas · ${showA?as.length:0} logros`;
      results.innerHTML=(showM&&ms.length?`<h3>Medallas de campeonato</h3><div class="stats-v8-achievements">${ms.map(m=>{
        const event=(data.competitions||[]).find(e=>Number(e.id)===Number(m.event_id)),earned=m.status==='ENTREGADA'&&deck.team_id&&Number(m.team_id)===Number(deck.team_id),delivered=m.status==='ENTREGADA',entered=event?.selected_deck_ids?.some(id=>Number(id)===Number(deck.id));
        const state=earned?'Obtenida':delivered?'Entregada a otro equipo':entered?'En disputa · Inscrito':'Sin inscripción';
        return `<article class="stats-v8-championship-card"><div class="stats-v8-award-heading"><i class="bi bi-trophy-fill" aria-hidden="true"></i><h4>${escape(m.name)}</h4></div><span class="stats-v8-award-status ${earned?'is-earned':''}">${state}</span><p>Gana ${escape(event?.name||m.name)}. El título debe estar confirmado para entregar la medalla.</p><small>Formato: ${escape((data.rulesets||[]).find(r=>r.code===m.format_id)?.label||m.format_id||'Sin confirmar')}</small><strong>Bono de campeonato: +${num(m.bonus_points)} puntos</strong>${delivered?`<small>Campeón: ${escape(name(m.team_id))}</small>`:''}</article>`;
      }).join('')}</div>`:'')+(showA&&as.length?'<h3>Logros estadísticos</h3>'+achievements(as):'');
      if(!results.innerHTML)results.innerHTML='<p class="note">No hay medallas ni logros con esta búsqueda.</p>';
    }
    search.oninput=render;kind.onchange=render;render();
    const parent=trigger.closest('.modal.show'),instance=root.bootstrap.Modal.getOrCreateInstance(modal);
    modal.addEventListener('hidden.bs.modal',()=>{if(parent?.matches('[data-deck-category-modal]'))document.querySelector('[data-deck-category-open="achievements"]')?.click();else if(parent)root.bootstrap.Modal.getOrCreateInstance(parent).show();else trigger.focus();},{once:true});
    if(parent){parent.addEventListener('hidden.bs.modal',()=>instance.show(),{once:true});root.bootstrap.Modal.getOrCreateInstance(parent).hide();}else instance.show();
  }
  document.addEventListener('click',event=>{const trigger=event.target.closest('[data-open-medal-catalog]');if(!trigger)return;const deck=(source().allDecks||[]).find(d=>Number(d.id)===Number(trigger.dataset.openMedalCatalog));if(deck)openMedalCatalog(deck,trigger);});
  function tierHistory(deck){return (source().statisticsV8?.publications||[]).filter(p=>p.kind==='metagame').flatMap(p=>(p.data.metagame||[]).filter(r=>Number(r.deck_id)===Number(deck.id)).map(r=>({...r,snapshot_on:p.cut_at,publication_id:p.id}))).sort((a,b)=>b.snapshot_on.localeCompare(a.snapshot_on));}
  const rankFormatLabel = code => ({tcg_advanced:'TCG',tcg_traditional:'TCG Tradicional',rush_duel:'RUSH DUEL',goat:'GOAT',ocg:'OCG'})[code] || (source().rulesets||[]).find(r=>r.code===code)?.label || code;
  function profileRankInfo(deck){
    const stats=source().statisticsV8,formats=[...new Set(deck.format_codes||[])];
    if(!formats.length)return {label:'Ranking global: sin formato',detail:'El deck no tiene un formato registrado.'};
    if (formats.some(format => root.YgoGlobalRanking?.isConstructed(format))) {
      const label = 'Ranking global TCG/OCG';
      if (!deck.team_id) return {label:label+': sin puesto',detail:'Asocia un equipo para consultar su clasificaci\u00f3n.'};
      if (!stats) return {label:label+': sin datos',detail:'No se ha podido consultar la clasificación actual.'};
      const pool = root.YgoGlobalRanking.build({...source(), allDecks:[...(source().allDecks || []), deck]});
      const row = pool.rows.find(row => row.deck_id === Number(deck.id));
      const coverage = `Catálogo completo: ${pool.total} decks TCG/OCG. Excluye Rush y GOAT.`;
      if (row?.position != null) return {label:`${label}: #${row.position} de ${pool.total}`,detail:coverage+' Puesto según resultados confirmados; incluye todas las ligas y divisiones.'};
      if (row?.state === 'INACTIVO') return {label:label+': inactivo',detail:coverage+' Sin puesto por inactividad.'};
      if (row?.state === 'REACTIVACION PENDIENTE') return {label:label+': pendiente de reactivación',detail:coverage+' Debe completar las partidas de reactivación.'};
      if (!deck.team_id) return {label:label+': sin puesto',detail:coverage+' Asocia un equipo para consultar su clasificación.'};
      return {label:label+': sin clasificar',detail:coverage+` En evaluación: ${row?.J || 0}/${stats.policy?.minimum_matches || 2} partidas confirmadas.`};
    }
    const views=formats.map(format=>{
      const label='Ranking global '+rankFormatLabel(format);
      if(!deck.team_id)return {label:label+': sin puesto',detail:'Asocia un equipo para consultar su clasificación.'};
      if(!stats)return {label:label+': sin datos',detail:'No se ha podido consultar la clasificación actual.'};
      const rows=(stats.global||[]).filter(r=>r.format_id===format);
      const row=rows.find(r=>Number(r.team_id)===Number(deck.team_id));
      const min=Number(stats.policy?.minimum_matches);
      const matches=Number(row?.J||0);
      if(row?.position!=null&&Number(row.position)>0)return {label:label+': #'+row.position+' de '+rows.filter(r=>r.position!=null).length,detail:'Clasificación actual del equipo en este formato, según resultados confirmados.'};
      if(row?.state==='INACTIVO')return {label:label+': inactivo',detail:'El equipo no ocupa un puesto por inactividad.'};
      if(row?.state==='REACTIVACION PENDIENTE')return {label:label+': pendiente de reactivación',detail:'El equipo debe completar las partidas de reactivación requeridas para recuperar un puesto.'};
      const progress=Number.isFinite(min)&&min>0
        ? `En evaluación: ${matches}/${min} partidas confirmadas. Obtiene puesto al completar ${min} partidas elegibles.`
        : 'Aún no reúne los requisitos para obtener un puesto en la clasificación.';
      return {label:label+': sin clasificar',detail:progress};
    });
    return {label:views.map(v=>v.label).join(' | '),detail:views.map(v=>formats.length>1?v.label.split(':')[0]+'. '+v.detail:v.detail).join(' ')};
  }
  function profileRank(deck){return profileRankInfo(deck).label;}
  function directoryPosition(deck){const formats=[...new Set(deck.format_codes||[])];if(formats.length!==1||!deck.team_id)return null;return (source().statisticsV8?.global||[]).find(r=>r.format_id===formats[0]&&Number(r.team_id)===Number(deck.team_id))?.position??null;}
  function profileMetric(deck){const r=(source().statisticsV8?.profiles||[]).find(r=>Number(r.team_id)===Number(deck.team_id));if(!r)return null;
    return {matches:r.J,wins:r.V,draws:r.E,losses:r.D,duelWins:r.VG,duelDraws:r.EG,duelLosses:r.DG,damageDealt:r.lp_net,damageReceived:r.lp_received,flawlessWins:r.flawless,perfectMatches:r.perfect_matches,
      firstStarts:r.first_n,firstWins:r.first_w,secondStarts:r.second_n,secondWins:r.second_w,brickHands:r.brick,brickWins:r.brick_w,d5Played:r.d5_n,d5Wins:r.d5_wins,comebackWins:r.comebacks,reverseSweeps:r.reverse_sweeps,bestMatchStreak:r.best_streak,
      turnsRecorded:r.turn_n,totalTurns:r.turn_sum,fastestWinTurns:r.fewest_turns,durationRecorded:r.duration_n,totalDuration:r.duration_sum,fastestWinSeconds:r.fastest,lpCovered:r.lp_n,lpTotal:r.JD,dicePlayed:r.dice_n,diceWins:r.dice_w,surrenderWins:r.surrender_w,surrenderLosses:r.surrender_l,deckOutWins:r.deck_out_w,timeWins:r.time_w,effectWins:r.effect_w};
  }
  function compatProfile(deck){
    const data=source().statisticsV8||{},r=(data.profiles||[]).find(r=>Number(r.team_id)===Number(deck.team_id)),metric=profileMetric(deck)||{};
    const out={points:null,matches:0,wins:0,draws:0,losses:0,duelWins:0,duelDraws:0,duelLosses:0,damageDealt:0,damageReceived:0,lpCovered:0,lpTotal:0,firstStarts:0,firstWins:0,secondStarts:0,secondWins:0,brickHands:0,brickWins:0,turnsRecorded:0,totalTurns:0,fastestWinTurns:null,durationRecorded:0,totalDuration:0,fastestWinSeconds:null,surrenderWins:0,surrenderLosses:0,lifePointWins:0,lifePointLosses:0,deckOutWins:0,timeWins:0,effectWins:0,flawlessWins:0,perfectMatches:0,dicePlayed:0,diceWins:0,d5Played:0,d5Wins:0,comebackWins:0,reverseSweeps:0,bestMatchStreak:0,opponents:new Map(),formats:new Set(deck.format_codes||[]),...metric};
    const ratio=(a,b)=>b?100*a/b:null;out.winRate=ratio(out.wins,out.matches);out.duelWinRate=ratio(out.duelWins,out.duelWins+out.duelDraws+out.duelLosses);out.firstWinRate=ratio(out.firstWins,out.firstStarts);out.secondWinRate=ratio(out.secondWins,out.secondStarts);out.brickRate=r?ratio(r.brick,r.brick_n):null;out.averageTurns=out.turnsRecorded?out.totalTurns/out.turnsRecorded:null;out.averageDuration=out.durationRecorded?out.totalDuration/out.durationRecorded:null;
    for(const m of data.confirmed_matches||[]){const side=Number(m.team_a_id)===Number(deck.team_id)?'A':Number(m.team_b_id)===Number(deck.team_id)?'B':null;if(!side)continue;const other=side==='A'?'B':'A',id=side==='A'?m.team_b_id:m.team_a_id;out.formats.add(m.format_id);
      if(!out.opponents.has(id))out.opponents.set(id,{name:name(id),matchWins:0,matchDraws:0,matchLosses:0,wins:0,draws:0,losses:0,duels:0});const row=out.opponents.get(id);row[m.result===side?'matchWins':m.result==='E'?'matchDraws':'matchLosses']++;
      for(const d of m.duels||[]){row.duels++;row[d.winner===side?'wins':d.winner==='E'?'draws':'losses']++;if(d.defeat_method==='life_points'){if(d.winner===side)out.lifePointWins++;else if(d.winner===other)out.lifePointLosses++;}}
    }
    const rivals=[...out.opponents.values()];out.rivalsBeaten=rivals.filter(r=>r.matchWins>0).length;out.rivalsFaced=rivals.length;out.topBeaten=rivals.filter(r=>r.matchWins).sort((a,b)=>b.matchWins-a.matchWins).slice(0,3);out.topLostTo=rivals.filter(r=>r.matchLosses).sort((a,b)=>b.matchLosses-a.matchLosses).slice(0,3);out.formatLabel=[...out.formats].join(', ')||'Sin actividad confirmada';return out;
  }
  async function mount(){
    return root.YgoStatisticsWorkspace?.mount({source,escape,num,table,name});
  }
  root.YgoStatistics={renderProfile,mount,tierHistory,profileRank,profileRankInfo,profileMetric,compatProfile,directoryPosition};
  document.addEventListener('DOMContentLoaded',mount);
})(window);
