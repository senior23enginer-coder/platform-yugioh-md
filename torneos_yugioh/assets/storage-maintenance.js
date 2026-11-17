(function(){
  'use strict';
  const marker='stationx_storage_cleanup_v1',retention=30*86400000;
  const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'America/Bogota',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  function cleanLocal(){
    try{
      const day=today();let previous=null;try{previous=JSON.parse(localStorage.getItem(marker)||'null');}catch(_){}
      if(previous?.day===day)return;
      const now=Date.now(),keys=[];
      for(let n=0;n<localStorage.length;n++)keys.push(localStorage.key(n));
      let removed=0;
      for(const key of keys){
        // Only notification receipts have a safe expiry. All drafts, results,
        // clocks, preferences and pending offline writes remain untouched.
        if(!/^dml-notified-\d+-\d{4}-\d{2}-\d{2}-\d{2}:\d{2}(?::\d{2})?$/.test(key||''))continue;
        const at=Date.parse(localStorage.getItem(key)||'');
        if(Number.isFinite(at)&&now-at>retention){localStorage.removeItem(key);removed++;}
      }
      localStorage.setItem(marker,JSON.stringify({day,at:now,removedNotifications:removed}));
    }catch(_){/* Storage may be disabled or full; never block the application. */}
  }
  function notifyWorker(){
    try{navigator.serviceWorker?.controller?.postMessage({type:'STATIONX_DAILY_STORAGE_CLEANUP'});}catch(_){}
  }
  function run(){cleanLocal();notifyWorker();}
  run();
  if('serviceWorker' in navigator){navigator.serviceWorker.addEventListener('controllerchange',notifyWorker);navigator.serviceWorker.ready.then(notifyWorker).catch(()=>{});}
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)run();});
  window.addEventListener('focus',run);
  window.setInterval(()=>{if(!document.hidden)run();},15*60000);
})();
