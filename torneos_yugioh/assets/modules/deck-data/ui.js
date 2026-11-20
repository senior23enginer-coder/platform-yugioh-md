(function (root) {
  'use strict';
  let deck=null, token='', ticket='', busy=false, dialog, status, preview, file;
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const endpoint=op=>`./?api=deck_data&deck_id=${Number(deck.id)}&op=${op}`;
  async function request(op,body){
    const res=await fetch(endpoint(op),{cache:'no-store',...(body?{method:'POST',headers:{'X-CSRF-Token':token},body}:{})});
    let result;try{result=await res.json();}catch{throw Error('El servidor no devolvió una respuesta válida. Comprueba el límite de subida.');}
    if(!res.ok||!result.ok)throw Error(result.message||'No se pudo completar la operación.');return result;
  }
  function uploadPreview(form){
    return new Promise((resolve,reject)=>{
      const xhr=new XMLHttpRequest();
      xhr.open('POST',endpoint('preview'));
      xhr.setRequestHeader('X-CSRF-Token',token);
      xhr.upload.onprogress=event=>{
        if(!event.lengthComputable)return;
        const percent=Math.min(100,Math.round(event.loaded*100/event.total));
        progress(percent,percent===100?'ZIP recibido. Validando cartas, guías y compatibilidad…':'Subiendo el ZIP: '+percent+' %');
      };
      xhr.upload.onload=()=>progress(null,'ZIP recibido. Validando el contenido…');
      xhr.onerror=()=>reject(Error('Se interrumpió la conexión durante la subida. Intenta de nuevo.'));
      xhr.ontimeout=()=>reject(Error('La subida excedió el tiempo permitido.'));
      xhr.onload=()=>{
        let response;try{response=JSON.parse(xhr.responseText);}catch{reject(Error('El servidor no devolvió una respuesta válida. Comprueba el límite de subida.'));return;}
        if(xhr.status<200||xhr.status>=300||!response.ok){reject(Error(response.message||'No se pudo preparar el ZIP.'));return;}
        resolve(response);
      };
      xhr.send(form);
    });
  }
  function progress(value,message){
    const container=dialog.querySelector('[data-dd-progress]');
    const bar=dialog.querySelector('[data-dd-progress-bar]');
    const label=dialog.querySelector('[data-dd-progress-label]');
    container.hidden=false;
    bar.removeAttribute('value');
    if(Number.isFinite(value))bar.value=value;
    label.textContent=message;
  }
  function updatedLabel(timestamp){
    if(!timestamp)return 'Última actualización de datos: sin fecha registrada';
    const date=new Date(timestamp);
    if(Number.isNaN(date.getTime()))return 'Última actualización de datos: sin fecha registrada';
    return 'Última actualización de datos: '+new Intl.DateTimeFormat('es-CO',{dateStyle:'medium',timeStyle:'short',timeZone:'America/Bogota'}).format(date);
  }
  function refreshUpdatedAt(selectedId){
    return fetch(`./?api=deck_data&deck_id=${Number(selectedId)}&op=status`,{cache:'no-store'}).then(res=>res.json()).then(data=>{
      if(Number(deck?.id)!==Number(selectedId)||!data.ok)return;
      const node=document.querySelector('[data-deck-last-updated]');
      if(node){node.textContent=updatedLabel(data.updated_at);node.title=data.updated_at?'Fecha de la última importación de datos del deck':'No hay fecha de importación de datos registrada';}
    }).catch(()=>{
      const node=document.querySelector('[data-deck-last-updated]');
      if(node&&Number(deck?.id)===Number(selectedId))node.textContent='Última actualización de datos: no disponible';
    });
  }
  function state(value){busy=value;dialog.querySelectorAll('button,input').forEach(b=>b.disabled=value);dialog.querySelector('[data-dd-commit]').disabled=value||!ticket;}
  function inspect(p){
    if(!p.candidate)return;
    if(!root.YgoCuratedStudy||!root.YgoDocumentedCombos)throw Error('No se cargaron los módulos de guías y editor. Recarga la plataforma.');
    for(const route of p.candidate.routes)root.YgoDocumentedCombos.convert(p.candidate,route,p.native_cards,p.skills||[]);
    const copy=JSON.parse(JSON.stringify(p.candidate)),neutral='data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
    function images(value){if(!value||typeof value!=='object')return;for(const key of Object.keys(value)){if(['image','full_image'].includes(key)&&typeof value[key]==='string')value[key]=neutral;else images(value[key]);}}
    images(copy);const host=document.createElement('div');
    try{root.YgoCuratedStudy.render(host,deck,copy,{cards:p.cards,sha256:'preview'});}catch(error){throw Error(`La data no se puede abrir en la guía: ${error.message}`);}finally{host.querySelector('[data-checked-study]')?._curatedDispose?.();}
  }
  async function action(fn){state(true);try{await fn();}catch(e){ticket='';status.textContent=e.message;status.setAttribute('role','alert');}finally{state(false);}}
  function init(){
    if(dialog)return;
    dialog=document.createElement('dialog');dialog.className='deck-data-dialog';dialog.setAttribute('aria-labelledby','deck-data-title');
    dialog.innerHTML=`<header><div><h2 id="deck-data-title">Importar / exportar Data</h2><p data-dd-name></p></div><button type="button" data-dd-close aria-label="Cerrar">×</button></header>
      <div class="deck-data-actions"><button type="button" data-dd-list><i class="bi bi-file-earmark-text" aria-hidden="true"></i><b>Exportar listado de cartas</b><span>Main, Extra, Side y Reserva, con nombres y copias.</span></button>
      <button type="button" data-dd-export><i class="bi bi-file-earmark-zip" aria-hidden="true"></i><b>Exportar data</b><span>ZIP con una única carpeta de este deck: documentos, imágenes, guías y combos.</span></button>
      <button type="button" data-dd-import><i class="bi bi-upload" aria-hidden="true"></i><b>Importar data</b><span>Selecciona el ZIP actualizado por ChatGPT para este deck.</span></button></div>
      <input type="file" accept=".zip,application/zip" data-dd-file hidden>
      <p class="deck-data-note">Exporta la carpeta de tu deck, adjunta el ZIP a ChatGPT y solicita que devuelva la misma carpeta actualizada. La plataforma prepara e importa su contenido; no necesitas Codex ni ejecutar scripts. Las instrucciones del paquete usan la estructura de Kashtira como referencia.</p>
      <p data-dd-status role="status" aria-live="polite"></p><div class="deck-data-progress" data-dd-progress hidden><label data-dd-progress-label for="deck-data-progress">Preparando el paquete…</label><progress id="deck-data-progress" data-dd-progress-bar max="100"></progress></div><section data-dd-preview hidden></section>
      <footer><button type="button" data-dd-cancel hidden>Elegir otro ZIP</button><button type="button" data-dd-commit class="btn btn-primary" hidden>Importar en este deck</button><button type="button" data-dd-reload hidden>Cerrar y volver a la ficha</button></footer>`;
    document.body.append(dialog);status=dialog.querySelector('[data-dd-status]');preview=dialog.querySelector('[data-dd-preview]');file=dialog.querySelector('[data-dd-file]');
    dialog.querySelector('[data-dd-close]').onclick=()=>{if(!busy)dialog.close();};
    dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});
    dialog.addEventListener('close',()=>document.querySelector('[data-deck-data-open]')?.focus());
    dialog.querySelector('[data-dd-list]').onclick=()=>{
      const button=document.createElement('button');button.hidden=true;button.dataset.deckDownload=String(deck.id);document.body.append(button);button.click();button.remove();status.textContent='Preparando el listado de cartas…';
    };
    dialog.querySelector('[data-dd-export]').onclick=()=>action(async()=>{
      status.textContent='Preparando el ZIP…';const res=await fetch(endpoint('export'),{cache:'no-store'});
      if(!res.ok){const error=await res.json();throw Error(error.message);}
      const url=URL.createObjectURL(await res.blob()),link=document.createElement('a');link.href=url;link.download=`${deck.name.replace(/[<>:"/\\|?*]/g,'_')}-Data.zip`;document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);status.textContent='ZIP descargado. Puedes adjuntarlo a ChatGPT.';
    });
    dialog.querySelector('[data-dd-import]').onclick=()=>file.click();
    dialog.querySelector('[data-dd-cancel]').onclick=()=>file.click();
    file.onchange=()=>{const selected=file.files[0];file.value='';if(!selected)return;action(async()=>{
      ticket='';preview.hidden=true;dialog.querySelector('[data-dd-commit]').hidden=true;dialog.querySelector('[data-dd-reload]').hidden=true;
      if(!/\.zip$/i.test(selected.name)||selected.size>48*1024*1024)throw Error('Selecciona un ZIP de hasta 48 MB.');
      status.textContent='Preparando el ZIP para previsualizar…';progress(0,'Iniciando subida…');const form=new FormData();form.append('package',selected);const p=await uploadPreview(form);progress(null,'Comprobando que las guías se puedan visualizar…');inspect(p);ticket=p.ticket;
      const compatibility=p.compatibility||{},rules=Number(compatibility.calculator_rules||0),quality=compatibility.visual_quality||{};
      preview.innerHTML=`<div class="deck-data-import-heading"><span class="deck-data-kicker">Vista previa · no se ha importado todavía</span><h3>Actualizar ${esc(p.deck)}</h3><p>Este ZIP actualizará las guías y fichas de este deck. Revisa los cambios antes de confirmar.</p></div><h4>Contenido que se cargará</h4><dl><div><dt>Fichas de cartas</dt><dd>${p.cards}</dd></div><div><dt>Combos visuales totales</dt><dd>${p.routes}</dd></div><div><dt>Jugadas nuevas</dt><dd>${p.new_routes}</dd></div><div><dt>Combos que cambiarán</dt><dd>${p.changed_routes}</dd></div><div><dt>Combos que dejarían de incluirse</dt><dd>${p.removed_routes}</dd></div><div><dt>Reglas ejecutables declaradas</dt><dd>${rules}</dd></div></dl><h4>Compatibilidad y calidad de la guía</h4><p>${rules?'Las reglas declaradas requieren validación por el motor antes de ser ejecutables; importar el ZIP no certifica las jugadas.':'Este ZIP no aporta reglas ejecutables nuevas a la calculadora. Las guías y combos visuales pueden seguir importándose como documentación.'}</p><p class="deck-data-visual-readiness" role="note"><strong>Calidad visual:</strong> ${Number(quality.illustrated_steps||0)}/${Number(quality.steps||0)} pasos con fotografías; ${Number(quality.full_endboards||0)}/${Number(quality.routes||0)} campos finales con estructura verificada; ${Number(quality.illustrative_endboards||0)} campos representados por zonas con casillas ilustrativas. ${quality.visual_complete?'El paquete declara cobertura visual completa. La calculadora y legalidad se validan aparte.':'Guía documental parcial: puedes importarla, pero no se inventarán ubicaciones ni fotografías faltantes.'}</p><div class="deck-data-safety"><strong>Se conservará:</strong> la cantidad de cartas de Main, Extra, Side y Reserva, los resultados de torneos y ${p.saved_revisions} revisiones propias del editor. La versión anterior de las guías queda en el historial del servidor.</div>${compatibility.warning?`<p class="deck-data-warning">${esc(compatibility.warning)}</p>`:''}`;preview.hidden=false;
      dialog.querySelector('[data-dd-commit]').hidden=false;dialog.querySelector('[data-dd-cancel]').hidden=false;dialog.querySelector('[data-dd-progress]').hidden=true;status.textContent='Vista previa lista. No se ha modificado el deck todavía.';
    });};
    dialog.querySelector('[data-dd-commit]').onclick=()=>action(async()=>{
      status.textContent='Guardando la nueva versión del deck…';progress(null,'Actualizando guías y versiones en el servidor…');const form=new FormData();form.append('ticket',ticket);const p=await request('commit',form);ticket='';dialog.querySelector('[data-dd-commit]').hidden=true;dialog.querySelector('[data-dd-cancel]').hidden=true;
      dialog.querySelector('[data-dd-progress]').hidden=true;preview.innerHTML=`<div class="deck-data-finished"><h3>Importación completada</h3><p><strong>${esc(p.deck)}</strong> tiene ${Number(p.entry.cards)} fichas y ${Number(p.entry.routes)} combos disponibles en la versión actualizada.</p><p>Las ediciones propias y los resultados anteriores se han conservado.</p></div>`;status.textContent='La importación terminó correctamente. Cierra para regresar a la ficha actualizada.';dialog.querySelector('[data-dd-reload]').hidden=false;
    });
    dialog.querySelector('[data-dd-reload]').onclick=()=>{
      if(busy)return;
      sessionStorage.setItem('stationx:deck-data-return',String(deck.id));
      dialog.close();
      const url=new URL(root.location.href);
      url.hash='deck_ficha';
      root.history.replaceState(root.history.state,'',url.pathname+url.search+url.hash);
      root.location.reload();
    };
  }
  function mount(selected){deck=selected;const label=document.querySelector('[data-deck-last-updated]');if(label)label.textContent='Consultando última actualización…';refreshUpdatedAt(selected.id);}
  document.addEventListener('click',e=>{if(!e.target.closest('[data-deck-data-open]')||!deck)return;init();ticket='';preview.hidden=true;dialog.querySelector('[data-dd-progress]').hidden=true;status.textContent='';dialog.querySelector('[data-dd-name]').textContent=deck.name;
    for(const key of ['commit','cancel','reload'])dialog.querySelector(`[data-dd-${key}]`).hidden=true;
    dialog.showModal();action(async()=>{const result=await request('status');token=result.token;status.textContent=result.has_data?`${result.cards} fichas y ${result.routes} jugadas disponibles para exportar.`:'Este deck aún no tiene contenido editorial cargado.';});
  });
  root.YgoDeckData={mount};
})(window);
