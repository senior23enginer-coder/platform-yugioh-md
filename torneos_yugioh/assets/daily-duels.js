(function(root){
  'use strict';
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const pending=v=>['PENDIENTE','EN CURSO','REQUIERE D5'].includes(String(v||'PENDIENTE').trim().toUpperCase());
  function build(data,matches){
    const events=new Map((data.competitions||[]).filter(c=>!['cancelled','canceled','anulado','cancelado','closed','completed','finished'].includes(String(c.status||'').toLowerCase())).map(c=>[Number(c.id),c]));
    const rows=(matches||[]).filter(m=>m.id&&m.scheduled_on===data.serverToday&&pending(m.estado||m.status)&&(!m.competition_id||events.has(Number(m.competition_id)))).map(m=>({...m,kind:'match',event:events.get(Number(m.competition_id))}));
    for(const m of data.ffaMatches||[]){if(m.scheduled_on!==data.serverToday||!pending(m.status||m.state?.status)||!events.has(Number(m.competition_id)))continue;rows.push({...m,kind:'ffa',event:events.get(Number(m.competition_id)),estado:m.status||m.state?.status});}
    return [...new Map(rows.map(m=>[m.kind+':'+m.id,m])).values()].sort((a,b)=>String(a.starts_at||'99:99').localeCompare(String(b.starts_at||'99:99'))||Number(a.id)-Number(b.id));
  }
  let initialized=false;
  function init(data,getMatches,openMatch){
    if(initialized||!root.bootstrap)return;initialized=true;
    const modal=document.createElement('div');modal.id='dailyDuelsModal';modal.className='modal fade dml-modal daily-duels-modal';modal.tabIndex=-1;modal.setAttribute('aria-labelledby','dailyDuelsTitle');modal.setAttribute('aria-hidden','true');
    modal.innerHTML='<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><span class="tag">Agenda de hoy</span><h2 class="modal-title" id="dailyDuelsTitle">Duelos pendientes para hoy</h2><p data-daily-duels-date></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><p data-daily-duels-count role="status"></p><div class="daily-duels-list" data-daily-duels-list></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cerrar</button></div></div></div>';
    document.body.appendChild(modal);const instance=root.bootstrap.Modal.getOrCreateInstance(modal);let shown=false;
    function show(){
      if(shown||document.querySelector('.modal.show, .modal.showing'))return;
      shown=true;document.removeEventListener('hidden.bs.modal',show);const rows=build(data,getMatches());
      modal.querySelector('[data-daily-duels-date]').textContent=String(data.serverToday||'').split('-').reverse().join('/')+' · Hora de Colombia';modal.querySelector('[data-daily-duels-count]').textContent=rows.length+' '+(rows.length===1?'duelo por completar':'duelos por completar');
      const list=modal.querySelector('[data-daily-duels-list]');list.innerHTML=rows.length?rows.map(m=>{
        const active=['EN CURSO','REQUIERE D5'].includes(String(m.estado||m.status).toUpperCase()),deck=id=>(data.allDecks||[]).find(d=>Number(d.id)===Number(id));
        const side=keys=>keys.map(k=>{const d=deck(m['deck_'+k+'_id']);return d?[d.team,d.name].filter(Boolean).join(' / '):'';}).filter(Boolean).join(' + ');
        const versus=m.kind==='ffa'?(m.state?.players||[]).map(p=>{const d=deck(p.deck_id);return d?[d.team,d.name].filter(Boolean).join(' / '):'Deck '+p.deck_id;}).join(' vs '):`${side(['a','c','e'])||m.a||'Por definir'} vs ${side(['b','d','f'])||m.b||'Por definir'}`;
        const league=m.event?.type==='league'||m.phase==='league';
        const eventName=m.event?.name||m.competition_name||'Amistoso';
        const division=data.divisions?.[m.division]||Object.values(data.divisions||{}).find(d=>Number(m.division_id)>0&&Number(d.id)===Number(m.division_id));
        const ownDivision=league&&division&&Number(division.league_competition_id)===Number(m.competition_id);
        let divisionName=ownDivision?String(division.name||division.short||m.division):'';
        if(divisionName.startsWith(eventName))divisionName=divisionName.slice(eventName.length).replace(/^[\s\u2014\u2013-]+/u,'');
        const divisionCode=ownDivision?(division.short||m.division||''):'';
        const context=`<p class="daily-duel-context"><strong>${league?'Liga':'Evento'}:</strong> ${esc(eventName)}${league?`<br><strong>Divisi\u00f3n:</strong> ${esc(divisionName||'Sin divisi\u00f3n registrada')}`:''}</p>`;
        return `<article class="daily-duel-card" data-daily-duel-key="${m.kind}:${Number(m.id)}"><div class="daily-duel-meta"><strong>${esc(String(m.starts_at||'').slice(0,5)||'Sin hora')}</strong><span class="status-chip ${active?'ok':''}">${active?'En curso':'Pendiente'}</span></div><h3>${esc(versus)}</h3>${context}<p>${m.ronda?`Ronda ${Number(m.ronda)}`:''}${m.mesa?` \u00b7 Mesa ${Number(m.mesa)}`:''}</p>${m.kind==='ffa'?`<a class="btn btn-outline-info" href="?event_id=${Number(m.competition_id)}#evento_ficha">Ver mesa</a>`:`<button type="button" class="btn btn-outline-info" data-daily-open-match="${Number(m.id)}">Ver duelo</button>`}</article>`;
      }).join(''):'<div class="empty-state">No tienes duelos pendientes para hoy.</div>';
      list.querySelectorAll('[data-daily-open-match]').forEach(button=>button.addEventListener('click',()=>{modal.addEventListener('hidden.bs.modal',()=>openMatch(button.dataset.dailyOpenMatch),{once:true});instance.hide();}));instance.show();
    }
    document.addEventListener('hidden.bs.modal',show);root.setTimeout(show,600);
  }
  root.YgoDailyDuels={build,init};
})(window);
