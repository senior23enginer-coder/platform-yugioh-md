(function(){
  'use strict';
  let active=null;
  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-card-inspect]');if(!trigger)return;
    event.preventDefault();event.stopPropagation();open(trigger);
  });
  function open(trigger){
    active?.();
    const id=Number(trigger.dataset.cardInspect);if(!Number.isInteger(id)||id<1)return;
    const overlay=document.createElement('div');overlay.className='card-inspector';
    overlay.innerHTML='<section role="dialog" aria-modal="true" aria-labelledby="card-inspector-title" class="card-inspector-dialog"><header><div><small>Inspector de carta</small><h3 id="card-inspector-title"></h3></div><button type="button" class="btn btn-outline-info" data-inspect-close aria-label="Cerrar inspector">✕</button></header><div class="card-inspector-tools"><button type="button" class="btn btn-outline-info" data-inspect-minus aria-label="Alejar">−</button><input type="range" min="25" max="400" step="5" value="100" aria-label="Zoom de la carta"><output>100%</output><button type="button" class="btn btn-outline-info" data-inspect-plus aria-label="Acercar">+</button><button type="button" class="btn btn-outline-info" data-inspect-fit>Ajustar</button><button type="button" class="btn btn-outline-info" data-inspect-original>100%</button></div><div class="card-inspector-viewport" tabindex="0" aria-label="Imagen ampliable. Usa la rueda para acercar y arrastra para desplazar."><img alt="" draggable="false"></div><p class="card-inspector-status" role="status" aria-live="polite">Cargando imagen original…</p></section>';
    const title=overlay.querySelector('h3'),viewport=overlay.querySelector('.card-inspector-viewport'),img=overlay.querySelector('img'),slider=overlay.querySelector('input'),output=overlay.querySelector('output'),status=overlay.querySelector('[role="status"]');
    title.textContent=trigger.dataset.cardName||'Carta '+id;img.alt=title.textContent;
    let scale=1,x=0,y=0,loaded=false,fallback=false,points=new Map(),gesture=null;
    function constrain(){const box=viewport.getBoundingClientRect();x=Math.max(-Math.max(0,(img.naturalWidth*scale-box.width)/2),Math.min(Math.max(0,(img.naturalWidth*scale-box.width)/2),x));y=Math.max(-Math.max(0,(img.naturalHeight*scale-box.height)/2),Math.min(Math.max(0,(img.naturalHeight*scale-box.height)/2),y));}
    function draw(){constrain();img.style.width=img.naturalWidth*scale+'px';img.style.height=img.naturalHeight*scale+'px';img.style.transform=`translate(calc(-50% + ${x}px),calc(-50% + ${y}px))`;slider.value=String(scale*100);output.textContent=Math.round(scale*100)+'%';}
    function zoom(value){if(!loaded)return;scale=Math.max(.25,Math.min(4,value));draw();}
    function fit(){if(!loaded)return;scale=Math.max(.25,Math.min(1,(viewport.clientWidth-24)/img.naturalWidth,(viewport.clientHeight-24)/img.naturalHeight));x=y=0;draw();}
    function close(){if(!overlay.isConnected)return;overlay.remove();window.removeEventListener('resize',fit);document.removeEventListener('keydown',key,true);points.clear();active=null;if(trigger.isConnected)trigger.focus({preventScroll:true});}
    function key(e){if(!overlay.isConnected)return;if(e.key==='Escape'){e.preventDefault();e.stopImmediatePropagation();close();}else if(e.key==='Tab'){const buttons=[...overlay.querySelectorAll('button,input,[tabindex="0"]')];const first=buttons[0],last=buttons.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}else if(!['INPUT'].includes(e.target.tagName)&&['+','=','-','0'].includes(e.key)){e.preventDefault();e.key==='0'?fit():zoom(scale+(e.key==='-'?-.1:.1));}}
    img.onload=()=>{loaded=true;fit();status.textContent=(fallback?'No se pudo cargar la imagen grande. Miniatura disponible: ':'Imagen original: ')+img.naturalWidth+' × '+img.naturalHeight+' píxeles. Rueda o pellizca para ampliar; arrastra para explorar.';};
    img.onerror=()=>{if(!fallback){fallback=true;img.src='./?api=deck_builder&op=image&id='+id;}else status.textContent='No se pudo cargar la imagen. Cierra el inspector e inténtalo de nuevo.';};
    overlay.querySelector('[data-inspect-close]').onclick=close;overlay.onclick=e=>{if(e.target===overlay)close();};
    slider.oninput=()=>zoom(Number(slider.value)/100);overlay.querySelector('[data-inspect-minus]').onclick=()=>zoom(scale-.15);overlay.querySelector('[data-inspect-plus]').onclick=()=>zoom(scale+.15);overlay.querySelector('[data-inspect-fit]').onclick=fit;overlay.querySelector('[data-inspect-original]').onclick=()=>zoom(1);
    viewport.addEventListener('wheel',e=>{e.preventDefault();zoom(scale*(e.deltaY<0?1.12:1/1.12));},{passive:false});
    viewport.onpointerdown=e=>{if(!loaded)return;e.preventDefault();viewport.setPointerCapture(e.pointerId);points.set(e.pointerId,{x:e.clientX,y:e.clientY});gesture={x,y,scale,points:[...points.values()]};};
    viewport.onpointermove=e=>{if(!points.has(e.pointerId)||!gesture)return;points.set(e.pointerId,{x:e.clientX,y:e.clientY});const now=[...points.values()],start=gesture.points;if(now.length===2&&start.length===2){const dist=p=>Math.hypot(p[0].x-p[1].x,p[0].y-p[1].y);zoom(gesture.scale*dist(now)/Math.max(1,dist(start)));}else if(now.length===1&&start.length===1){x=gesture.x+now[0].x-start[0].x;y=gesture.y+now[0].y-start[0].y;draw();}};
    function release(e){points.delete(e.pointerId);gesture=points.size?{x,y,scale,points:[...points.values()]}:null;}
    viewport.onpointerup=release;viewport.onpointercancel=release;viewport.onlostpointercapture=release;
    (trigger.closest('.modal')||document.body).append(overlay);active=close;document.addEventListener('keydown',key,true);window.addEventListener('resize',fit);overlay.querySelector('[data-inspect-close]').focus();img.src='./?api=deck_builder&op=image&size=full&id='+id;
  }
})();
