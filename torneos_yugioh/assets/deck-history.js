(function(root){
  'use strict';
  const instances=new WeakMap(),norm=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  function status(v){v=norm(v).replace(/[\s_-]+/g,'_');return ['en_curso','in_progress','requiere_d5','live'].includes(v)?'live':['finalizada','finalizado','completed','finished','closed'].includes(v)?'finished':['pendiente','pending','scheduled','programado'].includes(v)?'pending':v;}
  function today(){const parts=new Intl.DateTimeFormat('en-US',{timeZone:'America/Bogota',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()),p=Object.fromEntries(parts.map(p=>[p.type,p.value]));return `${p.year}-${p.month}-${p.day}`;}
  const eventLabel=v=>root.StationXLabels?.text(v)||String(v).replace(/^(.+) League$/,'Liga $1');
  function mount(body,deckId){
    if(!body)return;
    const section=body.closest('.deck-history-section');if(!section)return;
    let context=instances.get(body);
    if(!context){
      const controls=document.createElement('div');controls.className='deck-history-query';
      controls.innerHTML=`<div class="deck-history-filters"><label>Buscar rival o evento<input class="form-control" type="search" data-history-search placeholder="Deck, equipo, evento o mesa"></label><label>Evento<select class="form-select" data-history-event><option value="all">Todos los eventos</option></select></label><label>Estado<select class="form-select" data-history-state><option value="all">Todos los estados</option><option value="pending">Pendientes</option><option value="live">En curso</option><option value="finished">Finalizadas</option></select></label><label>Resultado<select class="form-select" data-history-result><option value="all">Todos los resultados</option><option value="Victoria">Victorias</option><option value="Derrota">Derrotas</option><option value="Empate">Empates</option><option value="Pendiente">Sin resultado final</option></select></label><label>Ronda<select class="form-select" data-history-round><option value="all">Todas las rondas</option></select></label><label>Período<select class="form-select" data-history-period><option value="all">Todas las fechas</option><option value="today">Hoy</option><option value="day">Por día</option><option value="month">Por mes</option><option value="range">Rango de fechas</option><option value="undated">Sin fecha</option></select></label><label>Consultar fecha de<select class="form-select" data-history-date-basis><option value="scheduled">Programación</option><option value="completed">Finalización</option></select></label><label>Orden<select class="form-select" data-history-order><option value="asc">Fecha: menor a mayor</option><option value="desc">Fecha: mayor a menor</option></select></label><label data-history-day-field hidden>Día<input class="form-control" type="date" data-history-day></label><label data-history-month-field hidden>Mes<input class="form-control" type="month" data-history-month></label><label data-history-range-field hidden>Desde<input class="form-control" type="date" data-history-from></label><label data-history-range-field hidden>Hasta<input class="form-control" type="date" data-history-to></label><div class="deck-history-clear"><button type="button" class="btn btn-outline-info" data-history-clear>Limpiar filtros</button></div></div><p class="deck-history-query-count" data-history-count role="status" aria-live="polite"></p>`;
      section.querySelector('.table-responsive').before(controls);
      const get=name=>controls.querySelector(`[data-history-${name}]`);
      context={controls,get,deckId:Number(deckId),rows:[]};instances.set(body,context);
      context.update=()=>{
        const period=get('period').value,from=get('from').value,to=get('to').value,invalid=period==='range'&&from&&to&&from>to;
        get('day-field').hidden=period!=='day';get('month-field').hidden=period!=='month';controls.querySelectorAll('[data-history-range-field]').forEach(n=>n.hidden=period!=='range');
        get('from').setCustomValidity(invalid?'La fecha desde debe ser anterior o igual a hasta.':'');get('to').setCustomValidity(invalid?'La fecha hasta debe ser posterior o igual a desde.':'');get('from').setAttribute('aria-invalid',String(!!invalid));get('to').setAttribute('aria-invalid',String(!!invalid));
        const query=norm(get('search').value.trim()),basis=get('date-basis').value==='completed'?'historyCompleted':'historyDate';
        const dates=row=>String(row.dataset[basis]||'').slice(0,10);
        const rows=[...context.rows].sort((a,b)=>{const x=dates(a),y=dates(b);if(!x||!y)return x?-1:y?1:0;return (get('order').value==='desc'?-1:1)*(x.localeCompare(y)||String(a.dataset.historyTime||'').localeCompare(String(b.dataset.historyTime||'')));});
        let count=0;
        for(const row of rows){const d=dates(row),ds=row.dataset;
          const inPeriod=period==='all'||(period==='undated'?!d:period==='today'?d===today():period==='day'?!get('day').value||d===get('day').value:period==='month'?!get('month').value||d.startsWith(get('month').value):(!from||d>=from)&&(!to||d<=to)&&(!from&&!to||!!d));
          const visible=!invalid&&inPeriod&&(!query||norm(row.textContent).includes(query))&&(get('event').value==='all'||get('event').value===ds.historyEvent)&&(get('state').value==='all'||get('state').value===status(ds.historyState))&&(get('result').value==='all'||get('result').value===ds.historyResult)&&(get('round').value==='all'||get('round').value===ds.historyRound);
          row.hidden=!visible;if(visible)count++;body.appendChild(row);
        }
        body.querySelector('[data-history-empty]')?.remove();
        if(!count){const row=document.createElement('tr');row.dataset.historyEmpty='';const cell=document.createElement('td');cell.colSpan=8;cell.textContent=invalid?'Revisa el rango: la fecha desde no puede ser posterior a hasta.':context.rows.length?'No hay partidas con los filtros actuales.':'Este deck todavía no tiene partidas registradas.';row.appendChild(cell);body.appendChild(row);}
        get('count').textContent=invalid?'Rango de fechas inválido.':`${count} de ${context.rows.length} partidas`;get('count').classList.toggle('is-error',!!invalid);
      };
      context.reset=()=>{get('search').value='';['event','state','result','round','period'].forEach(n=>get(n).value='all');get('date-basis').value='scheduled';get('order').value='asc';['day','month','from','to'].forEach(n=>get(n).value='');context.update();};
      controls.addEventListener('input',context.update);controls.addEventListener('change',context.update);get('clear').onclick=context.reset;
    }
    context.rows=[...body.querySelectorAll('[data-deck-history-row]')];
    body.querySelectorAll('tr:not([data-deck-history-row])').forEach(row=>row.remove());
    const options=(name,entries,label)=>{const select=context.get(name),value=select.value;select.replaceChildren(new Option(label,'all'),...entries.map(([value,label])=>new Option(label,value)));select.value=[...select.options].some(o=>o.value===value)?value:'all';};
    const events=new Map();for(const row of context.rows)events.set(row.dataset.historyEvent,eventLabel(row.querySelector('[data-label="Evento"] strong')?.textContent||'Sin evento'));
    options('event',[...events].sort((a,b)=>a[1].localeCompare(b[1],'es')),'Todos los eventos');
    const rounds=[...new Set(context.rows.map(row=>row.dataset.historyRound).filter(Boolean))].sort((a,b)=>Number(a)-Number(b));options('round',rounds.map(r=>[r,`Ronda ${r}`]),'Todas las rondas');
    if(context.deckId!==Number(deckId)){context.deckId=Number(deckId);context.reset();}else context.update();
  }
  root.YgoDeckHistory={mount};
})(window);
