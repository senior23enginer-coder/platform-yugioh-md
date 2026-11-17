(function(root){
  'use strict';
  let pending=null;
  function visible(){return !document.hidden&&['estadisticas','deck_ficha','deck'].some(key=>{const n=document.querySelector(`[data-screen="${key}"]`);return n&&!n.hidden;});}
  async function refresh(){
    if(!visible())return;
    if(pending)return pending;
    pending=(async()=>{
      try{
        const response=await fetch('?api=statistics',{cache:'no-store'});
        if(!response.ok)throw Error('No se pudo actualizar la clasificación.');
        const data=await response.json();if(!Array.isArray(data.global))throw Error('Respuesta estadística incompleta.');
        const previous=root.DML_DATA?.statisticsV8;
        root.DML_DATA=root.DML_DATA||{};root.DML_DATA.statisticsV8=data;
        if(JSON.stringify(previous)!==JSON.stringify(data))root.dispatchEvent(new CustomEvent('ygo:statistics-updated'));
        root.dispatchEvent(new CustomEvent('ygo:statistics-status',{detail:{ok:true}}));
      }catch(error){root.dispatchEvent(new CustomEvent('ygo:statistics-status',{detail:{ok:false,message:error.message}}));}
      finally{pending=null;}
    })();return pending;
  }
  root.YgoStatisticsLive={refresh};
  document.addEventListener('DOMContentLoaded',()=>{refresh();root.setInterval(refresh,30000);});
  root.addEventListener('focus',refresh);root.addEventListener('hashchange',refresh);
  document.addEventListener('visibilitychange',refresh);
})(window);
