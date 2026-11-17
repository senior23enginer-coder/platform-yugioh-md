(function(root){
  'use strict';
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const norm=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  const finished=v=>['FINALIZADA','FINALIZADO','COMPLETED','CLOSED','FINISHED'].includes(String(v||'').toUpperCase());
  const live=v=>['EN CURSO','IN_PROGRESS','REQUIERE D5'].includes(String(v||'').toUpperCase());
  const types={qualifier:'Clasificatorio',tournament:'Torneo',league:'Liga',cup:'Copa',world:'Mundial',final:'Final',friendly:'Amistoso',special:'Especial'};
  const states={draft:'Borrador',pending_configuration:'Pendiente de configuración',pending_event:'Programado',scheduled:'Programado',active:'Activo',in_progress:'En curso',completed:'Finalizado',closed:'Cerrado',finished:'Finalizado',cancelled:'Cancelado',canceled:'Cancelado'};
  function eventState(event,data){
    const status=norm(event.status).replace(/\s+/g,'_'),season=(data.seasons||[]).find(s=>Number(s.id)===Number(event.season_id));
    const closed=!!event.closed_at||!!season?.closed_at||['completed','closed','archived','finished'].includes(norm(season?.status));
    if(closed||['completed','closed','archived','finished','finalizado','finalizada'].includes(status))return 'completed';
    if(['active','in_progress','activo','en_curso'].includes(status))return 'active';
    if(Number(data.currentSeasonId)>0&&Number(event.season_id)===Number(data.currentSeasonId)&&['pending_event','scheduled','programado','pendiente_evento'].includes(status))return 'upcoming';
    return 'other';
  }
  function build(deck,data,matches){
    const id=Number(deck.id),unique=[...new Map((matches||[]).filter(m=>Number(m.id)>0).map(m=>[Number(m.id),m])).values()];
    const own=unique.filter(m=>['a','b','c','d','e','f'].some(s=>Number(m['deck_'+s+'_id'])===id));
    const ffa=(data.ffaMatches||[]).filter(m=>(m.state?.players||[]).some(p=>Number(p.deck_id)===id));
    return (data.competitions||[]).flatMap(event=>{
      const ms=own.filter(m=>Number(m.competition_id)===Number(event.id)),fs=ffa.filter(m=>Number(m.competition_id)===Number(event.id));
      const honors=(data.honors||[]).filter(h=>Number(h.competition_id)===Number(event.id)&&(Number(h.champion_deck_id)===id||Number(h.runner_up_deck_id)===id));
      const registered=(event.selected_deck_ids||[]).some(v=>Number(v)===id);
      if(!registered&&!ms.length&&!fs.length&&!honors.length)return [];
      const done=ms.filter(m=>finished(m.estado||m.status)),ffaDone=fs.filter(m=>finished(m.status||m.state?.status));
      const wins=done.filter(m=>{const side=['a','c','e'].some(s=>Number(m['deck_'+s+'_id'])===id)?'A':'B';return m.result===side;}).length+ffaDone.filter(m=>Number(m.winner_deck_id||m.state?.winner)===id).length;
      const draws=done.filter(m=>m.result==='E').length;
      const dates=[...ms.map(m=>m.scheduled_on),...fs.map(m=>m.scheduled_on)].filter(Boolean).sort();
      const played=done.length+ffaDone.length,ongoing=ms.filter(m=>live(m.estado||m.status)).length+fs.filter(m=>live(m.status||m.state?.status)).length;
      const outcome=honors.some(h=>Number(h.champion_deck_id)===id)?'Campeón':honors.some(h=>Number(h.runner_up_deck_id)===id)?'Subcampeón':null;
      return [{event,registered,total:ms.length+fs.length,done:played,wins,draws,ongoing,outcome,first:dates[0]||null,last:dates.at(-1)||null,participation:outcome|| (ongoing?'En competición':played?'Con participación':'Inscrito · Sin partidas jugadas')}];
    }).sort((a,b)=>String(b.last||b.event.starts_on||'').localeCompare(String(a.last||a.event.starts_on||''))||Number(b.event.id)-Number(a.event.id));
  }
  function render(node,deck,data,matches){
    if(!node)return [];
    const rows=build(deck,data,matches),played=rows.filter(r=>r.done||r.ongoing||r.outcome).length;
    const date=v=>v?String(v).slice(0,10).split('-').reverse().join('/'):'Sin fecha';
    node.innerHTML=`<div class="panel-head"><div><h3>Historial de eventos</h3><p>Participaciones e inscripciones de ${esc(deck.name)}. Cada evento aparece una sola vez.</p></div></div><p class="deck-events-summary">${rows.length} eventos registrados · ${played} con participación · ${rows.length-played} sin partidas jugadas</p><div class="deck-events-filters"><label>Buscar evento<input class="form-control" type="search" data-deck-events-search placeholder="Nombre, temporada o formato"></label><label>Estado del evento<select class="form-select" data-deck-events-state><option value="all">Todos los eventos</option><option value="active">Eventos activos</option><option value="upcoming">Próximos de esta temporada</option><option value="completed">Eventos finalizados</option></select></label><label>Participación<select class="form-select" data-deck-events-filter><option value="all">Toda participación</option><option value="played">Con participación</option><option value="registered">Sin partidas jugadas</option></select></label></div><p role="status" data-deck-events-count></p><div class="deck-events-list" data-deck-events-list></div>`;
    const search=node.querySelector('[data-deck-events-search]'),state=node.querySelector('[data-deck-events-state]'),filter=node.querySelector('[data-deck-events-filter]'),list=node.querySelector('[data-deck-events-list]');
    function update(){
      const selected=rows.filter(r=>{const p=!!(r.done||r.ongoing||r.outcome),format=(data.rulesets||[]).find(f=>f.code===r.event.event_ruleset)?.label||r.event.event_ruleset||'';return (state.value==='all'||state.value===eventState(r.event,data))&&(filter.value==='all'||(filter.value==='played'?p:!p))&&norm([r.event.name,r.event.season_name,format,types[r.event.type]||r.event.type].join(' ')).includes(norm(search.value));});
      node.querySelector('[data-deck-events-count]').textContent=`${selected.length} ${selected.length===1?'evento':'eventos'}`;
      list.innerHTML=selected.length?selected.map(r=>{
        const e=r.event,format=(data.rulesets||[]).find(f=>f.code===e.event_ruleset)?.label||e.event_ruleset||'Sin formato',season=e.season_name||(data.seasons||[]).find(s=>Number(s.id)===Number(e.season_id))?.name||'Sin temporada';
        const category=eventState(e,data),badge=category==='active'?'Evento activo':category==='upcoming'?'Próximo · Esta temporada':category==='completed'?'Evento finalizado':states[e.status]||e.status||'Sin estado';
        return `<article class="deck-event-history-card is-${category}" data-deck-event-id="${Number(e.id)}"><div class="deck-event-history-heading"><div><small>${esc(types[e.type]||e.type)} · ${esc(season)}</small><h4>${esc(e.name)}</h4><p>${esc(format)}</p></div><div class="deck-event-history-badges"><span class="deck-event-state">${esc(badge)}</span><span class="status-chip">${esc(r.participation)}</span></div></div><dl><div><dt>Combates completados</dt><dd>${r.done} / ${r.total}</dd></div><div><dt>Ganados / Empatados</dt><dd>${r.wins} / ${r.draws}</dd></div><div><dt>Primera fecha del deck</dt><dd>${date(r.first)}</dd></div><div><dt>Última fecha programada</dt><dd>${date(r.last)}</dd></div></dl>${r.ongoing?`<p>${r.ongoing} ${r.ongoing===1?'combate en curso':'combates en curso'}</p>`:''}<a class="btn btn-outline-info" href="?event_id=${Number(e.id)}&season_context=${Number(e.season_id)}#evento_ficha"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Ver evento</a></article>`;
      }).join(''):'<p class="note">'+(rows.length?'No hay eventos con estos filtros.':'Este deck todavía no tiene participaciones ni inscripciones registradas en eventos.')+'</p>';
    }
    search.oninput=update;filter.onchange=update;state.onchange=update;update();return rows;
  }
  root.YgoDeckEvents={build,render,eventState};
})(window);
