(function(root){
  'use strict';
  const data=root.DML_DATA||{},ordinals=['Primera','Segunda','Tercera','Cuarta','Quinta','Sexta','Séptima','Octava','Novena','Décima'];
  const eventName=name=>String(name||'').replace(/^(.+?) League$/u,'Liga $1').replace(/\binaguracion temporada\b/iu,'inauguración de temporada');
  function fallbackCatalog(){
    const out={events:{},events_by_id:{},divisions:{},division_short:{},cups:[]};
    for(const event of data.competitions||[]){const name=eventName(event.name);out.events[event.name]=name;out.events_by_id[event.id]={name,type:event.type};if(event.type==='cup')out.cups.push(name);}
    for(const [code,division]of Object.entries(data.divisions||{})){
      const event=out.events_by_id[division.league_competition_id];let short=division.name||division.short||code;
      if(event?.type==='league'){const own=Object.values(data.divisions).filter(d=>String(d.league_competition_id)===String(division.league_competition_id)).sort((a,b)=>Number(a.tier_order)-Number(b.tier_order)||Number(a.id)-Number(b.id)),index=own.findIndex(d=>Number(d.id)===Number(division.id));short=own.length===1?'División Única':(ordinals[index]?ordinals[index]+' División':'División '+(index+1));out.divisions[code]=event.name+' — '+short;}else out.divisions[code]=short;
      out.division_short[code]=short;
    }
    return out;
  }
  const catalog=root.StationXPresentationCatalog||data.presentationLabels||fallbackCatalog();
  const entityNames=new Set([...(data.allDecks||[]).map(d=>d.name),...(data.teams||[]).map(t=>t.name),...(data.players||[]).flatMap(p=>[p.alias,p.display_name])].filter(Boolean));
  const ruleNames=Object.fromEntries((data.gameRules||[]).filter(r=>r.code&&r.name).map(r=>[r.code,r.code==='CUSTOM_XP'?'Regla de puntuación personalizada':r.name]));
  const labels={tcg_advanced:'TCG',tcg_traditional:'TCG Tradicional',rush_duel:'RUSH DUEL',goat:'GOAT',ocg:'OCG',
    active:'Activo',pending_event:'Programado',pending_configuration:'Pendiente de configuración',scheduled:'Programado',completed:'Finalizado',finished:'Finalizado',closed:'Cerrado',cancelled:'Cancelado',canceled:'Cancelado',archived:'Archivado',paused:'Pausado',running:'En curso',setup:'Preparación',ready:'Listo',
    league:'Liga',cup:'Copa',qualifier:'Clasificatorio',tournament:'Torneo',world:'Mundial',friendly:'Amistoso',special:'Especial',events:'Eventos',event:'Evento',dashboard:'Panel principal',
    round_robin:'Todos contra todos',double_round_robin:'Todos contra todos, ida y vuelta',single_elimination:'Eliminación directa',double_elimination:'Doble eliminación',swiss:'Suizo',groups:'Fase de grupos',groups_knockout:'Grupos y eliminación',playoffs:'Fase final',ladder:'Escalera',flights:'Grupos independientes',king_of_the_hill:'Rey de la colina',gauntlet:'Desafío consecutivo',battle_royale:'Battle Royale',free_for_all:'Todos contra todos',pegasus_challenge:'Pegasus Challenge',
    fixed_weekly:'Calendario semanal fijo',consecutive:'Días consecutivos',alternate:'Días alternos',manual:'Manual',all_active:'Todos los participantes activos',
    individual_1v1:'Individual (1 contra 1)',team_3v3:'Equipos (3 contra 3)',tag_2v2:'Tag Duel (2 contra 2)',tag_duel:'Tag Duel',constructed:'Construido',sealed:'Sellado',
    ranking_threshold:'Corte por posición',points_threshold:'Puntos mínimos',best_record:'Mejor registro',runner_up:'Subcampeón',allowed:'Permitido',not_allowed:'No permitido',use_tiebreaker:'Aplicar desempate',full:'Completo',reduced:'Reducido',bonus:'Bonificación',none:'Sin efecto',
    life_points:'Puntos de vida',time_limit:'Tiempo agotado',deck_out:'Agotamiento del deck',effect_win:'Efecto de carta',surrender:'Rendición',tie_break:'Desempate',manual_match_correction:'Corrección manual del duelo',save_result:'Resultado guardado',append_turn:'Turno registrado',duel_surrendered:'Rendición registrada',match:'Duelo',competition:'Evento',swap:'Intercambiar'};
  const spelling={ultimos:'Últimos',ultimo:'Último',proximos:'Próximos',proximo:'Próximo',todavia:'Todavía',division:'División',divisiones:'Divisiones',configuracion:'Configuración',programacion:'Programación',informacion:'Información',estadisticas:'Estadísticas',finalizacion:'Finalización',rendicion:'Rendición',numero:'Número',calculo:'Cálculo',clasificacion:'Clasificación',participacion:'Participación',eliminacion:'Eliminación',politica:'Política',opcion:'Opción',validacion:'Validación',correccion:'Corrección',duracion:'Duración',generacion:'Generación',campeon:'Campeón',subcampeon:'Subcampeón',accion:'Acción',rapidas:'Rápidas',rapida:'Rápida',dia:'Día',dias:'Días',periodo:'Período',modulo:'Módulo',historico:'Histórico',historicos:'Históricos',catalogo:'Catálogo',despues:'Después',tambien:'También',segun:'Según'};
  const escape=s=>s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
  const pattern=keys=>new RegExp('\\b('+keys.sort((a,b)=>b.length-a.length).map(escape).join('|')+')\\b','g');
  const rulePattern=Object.keys(ruleNames).length?pattern(Object.keys(ruleNames)):null;
  const enumPattern=new RegExp(pattern(Object.keys(labels)).source,'gi'),spellingPattern=new RegExp(pattern(Object.keys(spelling)).source,'gi');
  const eventPattern=Object.keys(catalog.events).length?pattern(Object.keys(catalog.events)):null;
  const divisionPattern=Object.keys(catalog.divisions).length?pattern(Object.keys(catalog.divisions)):null;
  const upper=(raw,value)=>raw===raw.toUpperCase()?value.toUpperCase():/^[A-ZÁÉÍÓÚÑ]/u.test(raw)?value[0].toUpperCase()+value.slice(1):value.toLowerCase();
  function text(value,context={}){
    let result=String(value??'');
    if(/^\s*(?:\{\s*"|\[\s*(?:\{|\[|"))/.test(result))return result;
    if(!context.division&&!context.status&&entityNames.has(result.trim()))return result;
    // A complete fixture label disambiguates formats such as GOAT from their league.
    result=result.replace(/\b([A-Z][A-Z0-9_]+)\s+R(\d+)\s*(?:[|·-]\s*)?M(\d+)\b/g,(all,code,round,table)=>{
      const name=catalog.divisions[code]||(code==='COPA'&&catalog.cups.length===1?catalog.cups[0]:null);
      return name?`${name} — Ronda ${round} — Mesa ${table}`:all;
    });
    if(eventPattern)result=result.replace(eventPattern,name=>catalog.events[name]);
    result=result.replace(/\bun código como (?=(?:CUSTOM_XP|EVENT_RULE_[A-Z_]+)\b)/g,'una regla como ');
    if(rulePattern)result=result.replace(rulePattern,code=>ruleNames[code]);
    result=result.replace(/\bSTATION_X_STATS_([\d.]+)\b/g,'Estadísticas, versión $1');
    result=result.replace(/\bTop Cut\b/g,'Corte de clasificación');
    if(divisionPattern)result=result.replace(divisionPattern,code=>{
      if(['GOAT','RUSH_DUEL'].includes(code)&&!context.division)return code;
      return catalog.divisions[code];
    });
    result=result.replace(/\bTCG Avanzado\b/g,'TCG');
    const enumText=result;
    result=result.replace(enumPattern,(raw,token,offset)=>{
      const left=enumText.slice(0,offset),right=enumText.slice(offset+raw.length);
      const isolated=enumText.trim().toLowerCase()===raw.toLowerCase();
      const entityReference=/^\s*$/.test(left)&&/^\s*#\d+\s*$/.test(right);
      const separated=(/^\s*$|[|·/]\s*$/.test(left))&&(/^\s*$|^\s*[|·/]/.test(right));
      return raw.includes('_')||context.status||isolated||separated||entityReference?labels[raw.toLowerCase()]:raw;
    });
    if(result.trim()==='draft'||context.status&&result.trim().toLowerCase()==='draft')result=result.replace(/draft/i,'Borrador');
    result=result.replace(/\bR(\d+)\s*(?:[|·-]\s*)?M(\d+)\b/g,'Ronda $1 — Mesa $2');
    result=result.replace(/\bBO([135])\b/g,'Mejor de $1');
    result=result.replace(spellingPattern,raw=>upper(raw,spelling[raw.toLowerCase()]));
    return result;
  }
  const excluded='script,style,noscript,textarea,input,pre,code,kbd,samp,[contenteditable]:not([contenteditable="false"]),[data-raw-text]';
  function context(element){return {division:!!element.closest('[data-deck-list-division-filter],[data-team-list-division-filter],[name*="division"],[data-label="Division"],[data-label="División"]'),status:!!element.closest('.status-chip,[data-label="Estado"],[name*="status"]')};}
  function normalize(scope){
    if(scope.nodeType===Node.TEXT_NODE){const parent=scope.parentElement;if(!parent||parent.closest(excluded))return;const next=text(scope.data,context(parent));if(next!==scope.data){if(parent.tagName==='OPTION'&&!parent.hasAttribute('value'))parent.setAttribute('value',parent.value);scope.data=next;}return;}
    if(scope.nodeType!==Node.ELEMENT_NODE||scope.closest(excluded)&&!scope.matches('input,textarea'))return;
    const walker=document.createTreeWalker(scope,NodeFilter.SHOW_TEXT);const nodes=[];while(walker.nextNode())nodes.push(walker.currentNode);nodes.forEach(normalize);
    [scope,...scope.querySelectorAll('[title],[aria-label],[placeholder]')].forEach(element=>{
      if(element.closest('pre,code,[contenteditable]:not([contenteditable="false"]),[data-raw-text]'))return;
      for(const attribute of ['title','aria-label','placeholder'])if(element.hasAttribute(attribute)){const value=element.getAttribute(attribute),next=text(value);if(value!==next)element.setAttribute(attribute,next);}
    });
  }
  let observer,frame=0;const pending=new Set();
  function flush(){frame=0;observer.disconnect();try{const scopes=[...pending];pending.clear();for(const scope of scopes)if(scope.isConnected)normalize(scope);}finally{observe();}}
  function observe(){observer.observe(document.body,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['title','aria-label','placeholder']});}
  function schedule(scope){pending.add(scope);if(!frame)frame=requestAnimationFrame(flush);}
  function install(){observer=new MutationObserver(records=>{for(const record of records){if(record.type==='childList'){record.addedNodes.forEach(node=>schedule(node));}else schedule(record.target);}});normalize(document.body);observe();}
  root.StationXLabels={text,division:code=>catalog.divisions[code]||code,event:eventName,refresh:()=>normalize(document.body)};
  if(document.body)install();else document.addEventListener('DOMContentLoaded',install,{once:true});
})(window);
