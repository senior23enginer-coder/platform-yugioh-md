(function () {
  'use strict';
  const codes = ['tag_duel','team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship','all_star_event'];
  const escape = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const data = window.DML_DATA || {};
  const competition = id => (data.competitions || []).find(c=>String(c.id)===String(id));
  const baseCode = c => (data.eventPromptTemplates||[]).find(t=>t.code===c?.prompt_template)?.base_template||c?.prompt_template;
  const deck = id => (data.allDecks || []).find(d=>Number(d.id)===Number(id));
  function matches() {
    const out=[];
    const walk = (x,round=0) => {if(!x || typeof x!=='object')return;if(x.id && x.deck_a_id){out.push({...x,round_number:x.round_number || round});return;}const next=Number(x.ronda || x.round_number || round);Object.values(x).forEach(value=>walk(value,next));};
    walk(data.fixtures);return out;
  }
  let openResult;
  window.StationXTeams = {
    isTeamMatch: match => Number(competition(match.competition_id)?.participants_per_side||1)>1 && codes.includes(baseCode(competition(match.competition_id))),
    openResult: match => openResult?.(match)
  };
  document.addEventListener('DOMContentLoaded', () => {
    function modal(title,selector) {
      const m=document.createElement('div');m.className='modal fade dml-modal';m.tabIndex=-1;m.dataset[selector]='';
      m.innerHTML=`<div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" method="post"><div class="modal-header"><h2>${title}</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-primary" type="submit" data-teams-save>Guardar</button></div></form></div>`;
      m.querySelector('form').addEventListener('submit',event=>{if(!navigator.onLine){event.preventDefault();window.alert('Necesitas conexion para guardar equipos o resultados. Los datos permanecen en este formulario.');}});
      document.body.append(m);return m;
    }
    const lineupModal=modal('Equipos y alineaciones','teamsLineupModal'),resultModal=modal('Resultados por equipo','teamsResultModal'),listModal=modal('Combates por equipo','teamsMatchesModal');
    listModal.querySelector('[data-teams-save]').remove();
    listModal.querySelector('form').addEventListener('submit',e=>e.preventDefault());
    let c=null,rows=[],locked=false,currentMatch=null;
    function selected(button){const id=button.closest('form')?.querySelector('[name="competition_id"]')?.value || new URL(location.href).searchParams.get('event_id');return competition(id);}
    function renderLineups(){
      const size=baseCode(c)==='tag_duel'?2:3;
      const taken=rows.flatMap(r=>r.decks).filter(Boolean).map(Number);
      const allowed=(data.allDecks || []).filter(d=>(c.event_ruleset==='custom' || (d.format_codes || []).includes(c.event_ruleset)) && (c.type!=='league' || !(data.leagueDeckAssignments || []).some(a=>String(a.deck_id)===String(d.id) && String(a.season_id)===String(c.season_id) && String(a.competition_id)!==String(c.id))));
      lineupModal.querySelector('.modal-body').innerHTML=`<input type="hidden" name="action" value="save_team_lineups"><input type="hidden" name="competition_id" value="${Number(c.id)}"><input type="hidden" name="team_lineups_json"><p>${escape(c.name)} · ${size}v${size} · ${escape(c.event_ruleset)}</p><p>Los puestos quedan fijos al programar. Un deck solo puede ocupar un puesto en este evento. ${size===2?'Tag usa una serie compartida entre ambos lados.':'Se registran tres matches individuales; dos victorias por puesto ganan el encuentro colectivo.'}</p>${rows.map((row,index)=>`<section class="panel mb-3"><label class="d-block">Nombre del equipo<input class="form-control bg-dark text-light" maxlength="80" required data-team-name="${index}" value="${escape(row.name)}" ${locked?'disabled':''}></label><div class="row g-3 mt-1">${Array.from({length:size},(_,slot)=>`<label class="col-12 col-md-4">Puesto ${slot+1}<select class="form-select bg-dark text-light" data-team-deck="${index}:${slot}" required ${locked?'disabled':''}><option value="">Selecciona deck</option>${allowed.map(d=>`<option value="${Number(d.id)}" ${Number(d.id)===Number(row.decks[slot])?'selected':''} ${taken.includes(Number(d.id)) && Number(d.id)!==Number(row.decks[slot])?'disabled':''}>${escape(d.name)} · ${escape((d.format_codes || []).join(', '))}</option>`).join('')}</select></label>`).join('')}</div>${locked?'':`<button type="button" class="btn btn-outline-danger mt-3" data-team-remove="${index}">Quitar equipo</button>`}</section>`).join('')}${locked?'<p>Las alineaciones estan fijadas y solo permiten consulta.</p>':'<button type="button" class="btn btn-outline-info" data-team-add>Agregar equipo</button>'}`;
      lineupModal.querySelector('[data-teams-save]').hidden=locked;
      lineupModal.querySelectorAll('[data-team-name]').forEach(input=>input.addEventListener('input',()=>{rows[Number(input.dataset.teamName)].name=input.value;}));
      lineupModal.querySelectorAll('[data-team-deck]').forEach(input=>input.addEventListener('change',()=>{const [i,s]=input.dataset.teamDeck.split(':').map(Number);rows[i].decks[s]=Number(input.value)||0;renderLineups();}));
      lineupModal.querySelectorAll('[data-team-remove]').forEach(button=>button.addEventListener('click',()=>{rows.splice(Number(button.dataset.teamRemove),1);renderLineups();}));
      lineupModal.querySelector('[data-team-add]')?.addEventListener('click',()=>{if(rows.length<64){rows.push({name:`Equipo ${rows.length+1}`,decks:Array(size).fill(0)});renderLineups();}});
    }
    document.querySelectorAll('[data-open-team-lineups]').forEach(button=>button.addEventListener('click',()=>{
      c=selected(button);if(!c)return;const size=baseCode(c)==='tag_duel'?2:3;
      rows=c.teamConfig?structuredClone(c.teamConfig.lineups):[1,2].map(n=>({name:`Equipo ${n}`,decks:Array(size).fill(0)}));
      locked=!!c.teamConfig?.frozen || !!c.configuration_locked && String(c.configuration_locked)!=='0' || ['completed','closed','cancelled','archived'].includes(c.status);
      renderLineups();bootstrap.Modal.getOrCreateInstance(lineupModal).show();
    }));
    lineupModal.querySelector('form').addEventListener('submit',()=>{lineupModal.querySelector('[name="team_lineups_json"]').value=JSON.stringify(rows.map(r=>({name:r.name,decks:r.decks})));});
    function label(config,captain){return config?.lineups.find(row=>Number(row.captain)===Number(captain))?.name || deck(captain)?.name || 'Equipo';}
    document.querySelectorAll('[data-open-team-matches]').forEach(button=>button.addEventListener('click',()=>{
      const event=selected(button);if(!event)return;
      const rows=matches().filter(m=>Number(m.competition_id)===Number(event.id));
      const champ=event.workflow?.complete?label(event.teamConfig,event.workflow.champion):'';
      listModal.querySelector('.modal-body').innerHTML=`<p>${escape(event.name)}</p>${champ?`<p class="alert alert-success">Campeon: ${escape(champ)}</p>`:''}${rows.length?rows.map(m=>`<section class="panel mb-3"><strong>${escape(label(event.teamConfig,m.deck_a_id))} vs ${escape(label(event.teamConfig,m.deck_b_id))}</strong><p>Ronda ${Number(m.ronda || m.round_number || 0)} · ${escape(m.scheduled_on)} · ${escape(m.estado)} · ${escape(m.score || '')}</p><button type="button" class="btn btn-outline-info" data-team-open-result="${Number(m.id)}">Ver / registrar resultados</button></section>`).join(''):'<p>Guarda las alineaciones y comienza el evento para programar la primera fase.</p>'}`;
      listModal.querySelectorAll('[data-team-open-result]').forEach(button=>button.addEventListener('click',()=>openResult(matches().find(m=>Number(m.id)===Number(button.dataset.teamOpenResult)))));
      bootstrap.Modal.getOrCreateInstance(listModal).show();
    }));
    openResult=match=>{
      if(listModal.classList.contains('show')){listModal.addEventListener('hidden.bs.modal',()=>openResult(match),{once:true});bootstrap.Modal.getInstance(listModal).hide();return;}
      if(!match)return;currentMatch=match;const event=competition(match.competition_id),config=event?.teamConfig;if(!config)return;
      const record=data.teamResults?.[match.id];const tag=config.kind==='tag';const size=tag?1:3;
      const left=config.lineups.find(r=>r.captain===match.deck_a_id),right=config.lineups.find(r=>r.captain===match.deck_b_id);if(!left || !right)return;
      const frozen=!!event.workflow?.complete || event.workflow?.history?.some(m=>Number(m.match_id)===Number(match.id)) || ['completed','closed','cancelled','archived'].includes(event.status);
      resultModal.querySelector('.modal-body').innerHTML=`<input type="hidden" name="action" value="save_team_result"><input type="hidden" name="match_id" value="${Number(match.id)}"><input type="hidden" name="team_revision" value="${Number(record?.revision || 0)}"><input type="hidden" name="team_sequences_json"><h3>${escape(left.name)} vs ${escape(right.name)}</h3><p>${tag?'Una serie compartida 2v2; no hay victorias individuales separadas.':'Completa los tres puestos. Los juegos de cada puesto se guardan por separado del resultado colectivo.'} BO${Number(match.best_of)}. Secuencias D1–D5 segun la regla aplicada.</p>${Array.from({length:size},(_,seat)=>`<section class="panel mb-3"><h4>${tag?'Serie compartida':`Puesto ${seat+1}: ${escape(deck(left.decks[seat])?.name)} vs ${escape(deck(right.decks[seat])?.name)}`}</h4><div class="row g-2">${Array.from({length:5},(_,duel)=>`<label class="col-6 col-md-2">D${duel+1}<select class="form-select bg-dark text-light" data-team-result="${seat}:${duel}" ${frozen?'disabled':''}>${[['','Sin jugar'],['A',left.name],['B',right.name],['E','Empate']].map(([v,t])=>`<option value="${v}" ${v===(record?.sequences?.[seat]?.[duel] || '')?'selected':''}>${escape(t)}</option>`).join('')}</select></label>`).join('')}</div>${record?`<p class="mt-2">Registrado: ${escape(record.evaluations[seat].winner)} · ${escape(record.evaluations[seat].score)}</p>`:''}</section>`).join('')}<p>Resultado colectivo: ${escape(record?.aggregate?.winner || 'Pendiente')} ${escape(record?.aggregate?.score || '')}</p>${frozen?'<p>El resultado ya fue utilizado por otra fase o el evento esta cerrado. Solo consulta.</p>':''}`;
      resultModal.querySelector('[data-teams-save]').hidden=frozen;
      bootstrap.Modal.getOrCreateInstance(resultModal).show();
    };
    resultModal.querySelector('form').addEventListener('submit',()=>{
      const event=competition(currentMatch.competition_id),count=event.teamConfig.kind==='tag'?1:3;
      const sequences=Array.from({length:count},(_,seat)=>Array.from({length:5},(_,duel)=>resultModal.querySelector(`[data-team-result="${seat}:${duel}"]`).value));
      resultModal.querySelector('[name="team_sequences_json"]').value=JSON.stringify(sequences);
    });
  });
})();
