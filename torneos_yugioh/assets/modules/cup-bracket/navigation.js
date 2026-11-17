(function(root){
  'use strict';
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let initialized=false;
  function init(openMatch){
    if(initialized)return;initialized=true;
    const dialog=document.createElement('dialog');dialog.className='cup-match-preview';
    dialog.setAttribute('aria-labelledby','cup-match-preview-title');document.body.append(dialog);
    let trigger=null;
    dialog.addEventListener('close',()=>trigger?.focus());
    dialog.addEventListener('keydown',e=>{if(e.key==='Escape')e.stopPropagation();});
    dialog.addEventListener('click',e=>{if(e.target.closest('[data-cup-preview-close]'))dialog.close();});
    document.addEventListener('click',e=>{
      const button=e.target.closest('.cup-tree-node[data-cup-preview],.cup-tree-node[data-schedule-open-match]');
      if(!button)return;
      if(button.dataset.scheduleOpenMatch){
        const modal=button.closest('.modal.show');
        const instance=modal&&root.bootstrap?.Modal.getInstance(modal);
        if(instance){modal.addEventListener('hidden.bs.modal',()=>openMatch(button.dataset.scheduleOpenMatch),{once:true});instance.hide();}
        else openMatch(button.dataset.scheduleOpenMatch);
        return;
      }
      let match;try{match=JSON.parse(button.dataset.cupPreview);}catch{return;}
      if(!Array.isArray(match.sides)||match.sides.length!==2||match.sides.some(side=>!side))return;
      trigger=button;
      (button.closest('.modal')||document.body).append(dialog);
      dialog.innerHTML=`<header><div><small>Combate por programar</small><h2 id="cup-match-preview-title">${esc(match.sides.join(' vs '))}</h2><p>${esc(match.competition)} · Ronda ${esc(match.round)} · Mesa ${esc(match.slot)}</p></div><button type="button" data-cup-preview-close aria-label="Cerrar ficha del combate">Cerrar</button></header><div class="cup-match-preview-sides">${match.sides.map((side,i)=>`<section>${match.logos?.[i]?`<img src="${esc(match.logos[i])}" alt="" width="90" height="90">`:''}<h3>${esc(side)}</h3></section>`).join('')}</div><p>Ambos participantes están definidos. Este cruce todavía no tiene fecha ni un combate registrado. El resultado podrá cargarse cuando se cree esta ronda mediante el avance del evento.</p><button type="button" data-cup-preview-close>Volver a la llave</button>`;
      dialog.showModal();
    });
  }
  root.YgoCupBracketNavigation={init};
})(window);
