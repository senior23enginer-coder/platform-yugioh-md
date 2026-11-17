(function(root){
  'use strict';
  function create(dialog){
    // Keep native dialog Escape handling from reaching the Bootstrap sheet.
    dialog.addEventListener('keydown',e=>{if(e.key==='Escape')e.stopPropagation();});
    const stage=dialog.querySelector('[data-photo-stage]'),img=stage.querySelector('img'),caption=dialog.querySelector('[data-photo-caption]');
    let scale=1,x=0,y=0,fit=1,trigger=null,lastDistance=0,bodyOverflow='';
    const pointers=new Map();
    function draw(){
      const maxX=Math.max(0,(img.naturalWidth*fit*scale-stage.clientWidth)/2),maxY=Math.max(0,(img.naturalHeight*fit*scale-stage.clientHeight)/2);
      x=Math.max(-maxX,Math.min(maxX,x));y=Math.max(-maxY,Math.min(maxY,y));
      img.style.transform=`translate(${x}px,${y}px) scale(${scale})`;
    }
    function zoom(value){scale=Math.min(Math.max(4,1/fit),Math.max(1,value));draw();}
    function resize(){
      if(!img.naturalWidth||!dialog.open)return;
      fit=Math.min(1,stage.clientWidth/img.naturalWidth,stage.clientHeight/img.naturalHeight);
      img.style.width=`${img.naturalWidth*fit}px`;img.style.height=`${img.naturalHeight*fit}px`;draw();
    }
    img.onload=()=>{resize();caption.textContent=`${img.alt} · ${img.naturalWidth} × ${img.naturalHeight} px. El zoom amplía la imagen original; el efecto también está disponible como texto en la ficha.`;};
    dialog.querySelectorAll('[data-photo-control]').forEach(b=>b.onclick=()=>{
      if(b.dataset.photoControl==='in')zoom(scale*1.25);
      if(b.dataset.photoControl==='out')zoom(scale/1.25);
      if(b.dataset.photoControl==='fit'){scale=1;x=y=0;resize();}
      if(b.dataset.photoControl==='natural')zoom(1/fit);
      if(b.dataset.photoControl==='close')dialog.close();
    });
    stage.addEventListener('wheel',e=>{e.preventDefault();zoom(scale*(e.deltaY<0?1.15:1/1.15));},{passive:false});
    stage.onpointerdown=e=>{stage.setPointerCapture(e.pointerId);pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});lastDistance=0;};
    stage.onpointermove=e=>{
      const previous=pointers.get(e.pointerId);if(!previous)return;
      pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});
      if(pointers.size===2){const [a,b]=[...pointers.values()],distance=Math.hypot(a.x-b.x,a.y-b.y);if(lastDistance)zoom(scale*distance/lastDistance);lastDistance=distance;}
      else if(scale>1){x+=e.clientX-previous.x;y+=e.clientY-previous.y;draw();}
    };
    stage.onpointerup=stage.onpointercancel=e=>{pointers.delete(e.pointerId);lastDistance=0;};
    const observer=new ResizeObserver(resize);observer.observe(stage);
    dialog.addEventListener('close',()=>{document.body.style.overflow=bodyOverflow;pointers.clear();trigger?.focus();});
    return {
      open(photo,button){trigger=button;bodyOverflow=document.body.style.overflow;document.body.style.overflow='hidden';scale=1;x=y=0;img.alt=photo.name;img.onerror=()=>{img.onerror=null;img.src=photo.image;};img.src=photo.full_image||photo.image;dialog.showModal();requestAnimationFrame(resize);},
      dispose(){if(dialog.open){dialog.close();document.body.style.overflow=bodyOverflow;}observer.disconnect();}
    };
  }
  root.YgoStudyPhotoViewer={create};
})(window);
