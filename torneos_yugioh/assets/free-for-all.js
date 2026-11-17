(function(){
  'use strict';
  const data=window.DML_DATA||{},esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const phase=v=>({WAITING:'Sin iniciar',DRAW:'Robo',STANDBY:'Preparacion',MAIN1:'Principal1',BATTLE:'Batalla',MAIN2:'Principal2',END:'Fin del turno',FINISHED:'Finalizado'})[v]||v;
  const reason=v=>({life_points:'LP agotados',surrender:'Rendicion',deck_out:'Deck agotado',effect:'Efecto confirmado'})[v]||v;
  const deck=id=>(data.allDecks||[]).find(d=>Number(d.id)===Number(id));
  document.addEventListener('DOMContentLoaded',()=>{
    const screen=document.querySelector('[data-screen="evento_ficha"]');if(!screen)return;
    const panel=document.createElement('section');panel.className='panel';panel.dataset.ffaWorkspace='';panel.hidden=true;screen.append(panel);
    let key='',current;
    const request=()=>window.crypto?.randomUUID?.()||'ffa_'+Date.now()+'_'+Math.random().toString(36).slice(2);
    function action(row,label,kind,fields=''){
      if(!data.canEditEvents||row.state.status==='FINALIZADA'||['completed','closed','archived','cancelled'].includes(current.status))return '';
      if(row.state.status==='PENDIENTE'&&kind!=='start'||row.state.status!=='PENDIENTE'&&kind==='start')return '';
      if(kind==='start'&&row.scheduled_on>data.serverToday)return `<p>El duelo podra iniciarse el ${esc(row.scheduled_on)}.</p>`;
      return `<form method="post" class="d-inline-flex gap-2 flex-wrap my-2"><input type="hidden" name="action" value="save_ffa_action"><input type="hidden" name="ffa_match_id" value="${row.id}"><input type="hidden" name="ffa_revision" value="${row.state.revision}"><input type="hidden" name="ffa_request" value="${request()}"><input type="hidden" name="ffa_kind" value="${kind}">${fields}<button class="btn btn-outline-info">${label}</button></form>`;
    }
    function render(){
      const id=screen.querySelector('[data-competition-config-id]')?.value||new URL(location.href).searchParams.get('event_id');
      current=(data.competitions||[]).find(c=>String(c.id)===String(id));
      const enabled=current?.execution_model==='free_for_all';panel.hidden=!enabled;
      screen.querySelectorAll('[data-competition-config-submit="start"],[data-competition-config-submit="schedule_cup"],[data-competition-config-submit="schedule_qualifier"]').forEach(b=>{if(enabled)b.hidden=true;});
      if(!enabled){key='';return;}const rows=(data.ffaMatches||[]).filter(r=>String(r.competition_id)===String(id));const next=id+':'+JSON.stringify(rows);if(next===key)return;key=next;
      const membership=(current.selected_deck_ids||[]).map(Number);
      const options=(data.allDecks||[]).filter(d=>membership.includes(Number(d.id))).filter(d=>current.event_ruleset==='custom'||(d.format_codes||[]).includes(current.event_ruleset));
      panel.innerHTML=`<h3>Battle Royale / Free-for-All</h3><p>3 o 4 jugadores independientes. Cada jugador tiene sus LP y turnos. Se saltan los eliminados; gana el ultimo superviviente. El orden elegido al crear la mesa fija los turnos. Sus resultados tienen un Elo propio en Estadisticas, Ranking, Battle Royale; no se convierten en combates1v1.</p>${data.canEditEvents&&!['completed','closed','archived','cancelled'].includes(current.status)?`<details><summary>Crear mesa multijugador</summary><form method="post" data-ffa-create><input type="hidden" name="action" value="create_ffa_match"><input type="hidden" name="competition_id" value="${id}"><p>Primero inscribe los decks desde Participantes. Los tres primeros puestos son obligatorios; el cuarto es opcional.</p><div class="row g-3">${[1,2,3,4].map(n=>`<label class="col-md-6">Jugador ${n}<select class="form-select" name="ffa_decks[]" ${n<4?'required':''}><option value="">${n===4?'Sin cuarto jugador':'Selecciona deck'}</option>${options.map(d=>`<option value="${d.id}">${esc(d.name)} · ${esc(d.team||'')}</option>`).join('')}</select></label>`).join('')}<label class="col-md-6">Fecha<input class="form-control" type="date" name="ffa_date" required value="${esc(data.serverToday)}"></label><label class="col-md-6">Hora<input class="form-control" type="time" name="ffa_time" value="19:00" required></label></div><button class="btn btn-primary mt-3">Crear duelo</button></form></details>`:''}${rows.map(row=>{
        const s=row.state,alive=s.players.filter(p=>!p.eliminated),now=s.players[s.current];
        const target=`<select class="form-select" name="ffa_deck_id">${alive.map(p=>`<option value="${p.deck_id}">${esc(deck(p.deck_id)?.name||p.deck_id)}</option>`).join('')}</select>`;
        return `<article class="panel mt-3" data-ffa-match="${row.id}"><h4>Mesa ${row.id} · ${esc(row.scheduled_on)} ${esc(row.starts_at)}</h4><p>${esc(s.status)} · Turno ${s.turn} · ${esc(phase(s.phase))}${s.winner?` · Ganador: ${esc(deck(s.winner)?.name||s.winner)}`:` · Juega: ${esc(deck(now.deck_id)?.name||now.deck_id)}`}</p><div class="row g-2">${s.players.map(p=>`<div class="col-md-6"><div class="panel"><b>${esc(deck(p.deck_id)?.name||p.deck_id)}</b><p>${p.lp} LP · ${p.turns} turnos · ${p.eliminated?'Eliminado: '+esc(reason(p.reason)):'En juego'}</p></div></div>`).join('')}</div>${action(row,'Iniciar duelo','start')}${action(row,'Siguiente fase','next_phase')} ${action(row,'Finalizar turno','next_turn')}<div>${action(row,'Aplicar LP','lp',target+'<input class="form-control" type="number" name="ffa_delta" required min="-1000000" max="1000000" placeholder="Cambio: -500 o 500">')}</div><div>${action(row,'Confirmar eliminacion','eliminate',target+'<select class="form-select" name="ffa_reason"><option value="surrender">Rendicion</option><option value="deck_out">Deck agotado</option><option value="effect">Efecto confirmado</option></select>')}</div><details><summary>Historial de acciones</summary><p>Las acciones se guardan con revision y fecha; no se pueden sobrescribir ni aplicar dos veces.</p><button type="button" class="btn btn-outline-info" data-ffa-history="${row.id}">Consultar historial</button><ol data-ffa-history-list="${row.id}"></ol></details></article>`;
      }).join('')||'<p>No hay mesas registradas.</p>'}`;
      if(data.canEditEvents&&rows.length&&rows.every(r=>r.state.status==='FINALIZADA')&&!['completed','closed','archived','cancelled'].includes(current.status))panel.insertAdjacentHTML('beforeend',`<form method="post"><input type="hidden" name="action" value="finalize_ffa"><input type="hidden" name="competition_id" value="${id}"><p>El campeon debe tener mas victorias que cualquier otro participante. Si hay empate se necesita otro duelo.</p><button class="btn btn-warning">Finalizar evento multijugador</button></form>`);
      panel.querySelectorAll('[data-ffa-history]').forEach(button=>button.addEventListener('click',async()=>{
        const list=panel.querySelector('[data-ffa-history-list="'+button.dataset.ffaHistory+'"]');
        try{const response=await fetch('?api=ffa_history&ffa_match_id='+button.dataset.ffaHistory,{cache:'no-store'});if(!response.ok)throw Error();const history=await response.json();
          list.innerHTML=history.actions.length?history.actions.map(row=>{const a=JSON.parse(row.payload);const label=({start:'Inicio del duelo',next_phase:'Cambio de fase',next_turn:'Final de turno',lp:'Cambio de LP: '+(a.delta||0),eliminate:'Eliminacion: '+reason(a.reason)})[a.kind]||a.kind;return '<li>'+esc(row.created_at)+' ? '+esc(label)+(a.deck_id?' ? '+esc(deck(a.deck_id)?.name||a.deck_id):'')+'</li>';}).join(''):'<li>Sin acciones registradas.</li>';
        }catch(e){list.innerHTML='<li>No se pudo cargar el historial.</li>';}
      }));
      panel.querySelector('[data-ffa-create]')?.addEventListener('submit',e=>{
        const selects=[...e.target.querySelectorAll('[name="ffa_decks[]"]')],ids=selects.map(s=>s.value).filter(Boolean);
        if(new Set(ids).size!==ids.length){e.preventDefault();alert('Cada jugador necesita un deck diferente.');return;}
        selects.filter(s=>!s.value).forEach(s=>s.disabled=true);
      });
    }
    render();setInterval(render,1000);window.addEventListener('hashchange',render);
  });
})();
