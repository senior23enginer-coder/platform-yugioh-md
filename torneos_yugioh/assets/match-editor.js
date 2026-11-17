(function(){
 'use strict';
 const node=document.querySelector('[data-match-editor]');if(!node||!window.bootstrap)return;
 const modal=bootstrap.Modal.getOrCreateInstance(node),form=node.querySelector('form'),fields=node.querySelector('[data-match-editor-fields]'),message=node.querySelector('[data-match-editor-message]'),save=node.querySelector('[data-match-editor-save]');
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let current=null,parent=null,saved=false,checking=0,timer,loading=0;
 const endpoint=()=>'?api=match_editor&match_id='+current.id;
 const show=(text,error=false)=>{message.textContent=text;message.className='alert '+(error?'alert-warning':'alert-info');if(error)message.scrollIntoView({block:'nearest'});};
 const select=(name,label,value,options)=>`<label>${esc(label)}<select class="form-select" name="${name}">${options.map(([v,t])=>`<option value="${esc(v)}" ${String(v)===String(value)?'selected':''}>${esc(t)}</option>`).join('')}</select></label>`;
 const input=(name,label,value,type='text',extra='')=>`<label>${esc(label)}<input class="form-control" name="${name}" type="${type}" value="${esc(value)}" ${extra}></label>`;
 function focusDuel(n){const row=fields.querySelector(`[data-editor-duel="${n}"]`);if(row){row.open=true;row.scrollIntoView({block:'nearest'});}}
 function schedule(){return Object.fromEntries(['scheduled_on','starts_at','venue','judge','notes'].map(k=>[k,form.elements.namedItem(k)?.value||'']));}
 async function availability(){if(!current)return false;const seq=++checking,params=new URLSearchParams({...schedule(),check:'schedule'});show('Comprobando disponibilidad en el calendario…');save.disabled=true;try{const r=await fetch(endpoint()+'&'+params,{cache:'no-store'}),d=await r.json();if(seq!==checking)return false;show(d.message,!d.ok);save.disabled=!d.ok;return !!d.ok;}catch{show('No se pudo comprobar la fecha. Reintenta con conexión.',true);return false;}}
 function render(response,draft){const s=response.snapshot,m=s.match,r=s.result,full=current.mode==='full';
  const stored=JSON.parse(r.duel_details||'[]'),live=JSON.parse(s.live.state_json||'null');
  let rows=Array.from({length:5},(_,i)=>{const d={...(stored.find(d=>Number(d.duel)===i+1)||{}),duel:i+1,winner:r['d'+(i+1)]||''};if(live&&Number(live.duel_number)===i+1&&!['FINALIZADA','COMPLETED'].includes(m.status))Object.assign(d,{lp_a:live.lp_a,lp_b:live.lp_b,turn_count:live.turn_count,starts_player:live.starts_player,duel_started:['running','paused'].includes(live.status),duration_seconds:live.elapsed_seconds});return d;});
  if(draft?.duels)rows=rows.map((d,i)=>({...d,...draft.duels[i],winner:draft.values?.[i]??d.winner}));
  const played=rows.filter(d=>d.winner).length,active=Math.max(1,Math.min(5,['FINALIZADA','COMPLETED'].includes(m.status)?played:(Number(live?.duel_number)||rows.findIndex(d=>!d.winner)+1||5)));
  node.querySelector('[data-match-editor-label]').textContent=response.label;current.snapshot=s;current.token=response.token;current.original=schedule;
  fields.innerHTML='<section><h3>Agenda de la partida</h3><div class="match-editor-grid">'+input('scheduled_on','Fecha',m.scheduled_on,'date','required')+input('starts_at','Hora',String(m.starts_at||'').slice(0,5),'time','required')+input('venue','Sede',m.venue)+input('judge','Juez',m.judge)+'</div>'+input('notes','Notas de agenda',m.notes||'')+'<p class="note">Se comprueban los días y horarios habilitados, participantes ocupados, capacidad diaria y fechas del evento y la temporada.</p></section>';
  if(full){fields.insertAdjacentHTML('beforeend','<section><h3>Resultados y estado de los duelos</h3><p>El número de turno empieza en 1. Puedes corregir el jugador actual y quién inició. Al guardar, el reloj queda pausado y se conserva una auditoría de la corrección.</p><div class="match-editor-grid">'+select('active_duel','Duelo a modificar',active,[1,2,3,4,5].map(n=>[n,'D'+n]))+select('winner','Ganador de la partida',r.result||'',[['','Aún sin ganador'],['A','Gana A'],['B','Gana B'],['E','Empate']])+'</div>'+'<p class="note">Seleccionar un D solo abre sus campos: no borra ni oculta los otros. El duelo en curso y el ganador se calculan con los resultados al revisar los cambios.</p><p class="note" data-editor-runtime></p>'+rows.map(d=>{
    const n=d.duel,turn=Number(d.turn_count||0)+1,start=d.starts_player||'',side=start?(turn%2===1?start:(start==='A'?'B':'A')):'';
    return `<details class="match-editor-duel" data-editor-duel="${n}" ${n===active?'open':''}><summary>D${n} · <span data-editor-result-label>${esc(d.winner?'Resultado '+d.winner:'Sin resultado')}</span></summary><div class="match-editor-grid">`+select(`d${n}_winner`,'Resultado',d.winner,[['','Sin jugar'],['A','Gana A'],['B','Gana B'],['E','Empate']])+select(`d${n}_defeat_method`,'Cómo terminó',d.defeat_method||'',[['','Sin definir'],['life_points','LP del rival en cero'],['surrender','Rendición'],['time_limit','Tiempo'],['deck_out','Deck agotado'],['effect_win','Efecto']])+input(`d${n}_lp_a`,'LP de A',d.lp_a===''||d.lp_a==null?8000:d.lp_a,'number','min="0" max="99999" required')+input(`d${n}_lp_b`,'LP de B',d.lp_b===''||d.lp_b==null?8000:d.lp_b,'number','min="0" max="99999" required')+input(`d${n}_current_turn`,'Número de turno',turn,'number','min="1" max="999" required')+select(`d${n}_starts_player`,'Quién empezó',start,[['','Sin definir'],['A','Lado A'],['B','Lado B']])+select(`d${n}_active_side`,'Turno actual de',side,[['','Sin definir'],['A','Lado A'],['B','Lado B']])+input(`d${n}_duration_seconds`,'Duración (segundos)',d.duration_seconds||0,'number','min="0" max="7200" required')+'</div>'+`<div class="match-editor-checks"><label><input type="checkbox" name="d${n}_duel_started" ${d.duel_started?'checked':''}> Duelo iniciado</label><label><input type="checkbox" name="d${n}_brick_a" ${d.brick_a?'checked':''}> Brick A</label><label><input type="checkbox" name="d${n}_brick_b" ${d.brick_b?'checked':''}> Brick B</label></div>`+input(`d${n}_notes`,'Notas del duelo',d.notes||'')+'</details>';
  }).join('')+input('result_notes','Notas de la partida',draft?.notes??r.notes??'')+'</section>');}
  window.StationXParticipants?.set(node,m);
  const winnerField=form.elements.namedItem('winner');if(winnerField)winnerField.disabled=true;
  show('Revisa los datos. Ningún cambio se guardará hasta confirmar.');save.disabled=false;
  if(!full)availability();
 }
 async function open(detail){const ticket=++loading;saved=false;current={id:Number(detail.matchId),mode:detail.mode||'full'};parent=detail.parentId?document.getElementById(detail.parentId):null;
  fields.innerHTML='';save.disabled=true;show('Cargando los datos actuales de la partida…');
  if(parent?.classList.contains('show')){await new Promise(resolve=>{parent.addEventListener('hidden.bs.modal',resolve,{once:true});bootstrap.Modal.getOrCreateInstance(parent).hide();});}
  modal.show();try{const r=await fetch(endpoint(),{cache:'no-store'}),d=await r.json();if(ticket!==loading)return;if(!r.ok||!d.ok)throw Error(d.message||'No se pudo cargar la partida.');render(d,detail.draft);}catch(e){show(e.message,true);}
 }
 form.addEventListener('invalid',e=>{const row=e.target.closest('[data-editor-duel]');if(row)row.open=true;},true);
 form.addEventListener('change',e=>{
  if(e.target.name==='active_duel')focusDuel(Number(e.target.value));
  const m=e.target.name.match(/^d([1-5])_(starts_player|active_side|current_turn)$/);if(m){const n=m[1],get=k=>form.elements.namedItem(`d${n}_${k}`),turn=Number(get('current_turn').value),other=s=>s==='A'?'B':s==='B'?'A':'';
   if(m[2]==='active_side')get('starts_player').value=turn%2===1?get('active_side').value:other(get('active_side').value);else get('active_side').value=turn%2===1?get('starts_player').value:other(get('starts_player').value);
  }
  const winner=e.target.name.match(/^d([1-5])_winner$/);if(winner)fields.querySelector(`[data-editor-duel="${winner[1]}"] [data-editor-result-label]`).textContent=e.target.value?'Resultado '+e.target.value:'Sin resultado';
  if(['scheduled_on','starts_at','venue'].includes(e.target.name)){clearTimeout(timer);timer=setTimeout(availability,200);}
 });
 form.addEventListener('submit',async e=>{e.preventDefault();if(!current||!form.reportValidity())return;const s=schedule(),old=current.snapshot.match;
  if(current.mode==='schedule'||s.scheduled_on!==old.scheduled_on||s.starts_at!==String(old.starts_at||'').slice(0,5)||s.venue!==String(old.venue||'')){if(!await availability())return;}
  const value=name=>form.elements.namedItem(name)?.value||'',payload={mode:current.mode,version:current.snapshot.version,token:current.token,schedule:s,confirmed:true};
  if(current.mode==='full'){payload.active_duel=Number(value('active_duel'));payload.winner=value('winner');payload.result_notes=value('result_notes');payload.duels=Array.from({length:5},(_,i)=>{const n=i+1,out={duel:n};for(const k of ['winner','defeat_method','lp_a','lp_b','current_turn','starts_player','active_side','duration_seconds','notes'])out[k]=value(`d${n}_${k}`);for(const k of ['duel_started','brick_a','brick_b'])out[k]=form.elements.namedItem(`d${n}_${k}`).checked;return out;});}
  save.disabled=true;show('Revisando los cambios antes de confirmar...');try{
   let summary='';
   if(current.mode==='full'){
    const response=await fetch(endpoint(),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...payload,preview:true})}),preview=await response.json();
    if(!response.ok||!preview.ok)throw Error(preview.message||'Revisa los resultados antes de guardar.');
    payload.winner=preview.winner||'';form.elements.namedItem('winner').value=payload.winner;
    summary=preview.status==='FINALIZADA'?`Partida finalizada. Marcador: ${preview.score}. Ganador: ${preview.winner}.`:`Duelo en curso: D${preview.active_duel}. Marcador: ${preview.score}.`;
    fields.querySelector('[data-editor-runtime]').textContent=summary;
   }
   if(!window.confirm('Confirma que deseas guardar estos cambios.\n'+summary+'\nLa correccion quedara registrada en auditoria.')){save.disabled=false;show('Cambios sin guardar. Puedes seguir modificando el formulario.');return;}
   show('Guardando los cambios confirmados...');
   const r=await fetch(endpoint(),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.message||'No se pudo guardar.');saved=true;
   if(current.mode==='full'){localStorage.setItem('dml_result_capture_match_id',String(current.id));localStorage.setItem('dml_result_capture_route','resultados');for(let n=1;n<=5;n++){localStorage.removeItem(`dml_duel_timer_${current.id}_${n}`);localStorage.removeItem(`dml_result_lp_${current.id}_${n}`);}}
   location.reload();
  }catch(e){show(e.message,true);save.disabled=false;}
 });
 node.addEventListener('hidden.bs.modal',()=>{checking++;clearTimeout(timer);if(!saved&&parent){if(parent.id==='resultCaptureModal')window.dispatchEvent(new CustomEvent('dml:resume-result',{detail:{matchId:current.id}}));else bootstrap.Modal.getOrCreateInstance(parent).show();}});
 window.addEventListener('dml:modify-match',e=>open(e.detail));
})();
