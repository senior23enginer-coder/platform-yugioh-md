const CACHE_NAME = 'yugioh-md-match-v220';
const APP_SHELL = [
  './assets/modules/match-sheet/entity-links.js?v=20261117-entity-links',
  './assets/modules/match-sheet/entity-links.css?v=20261117-entity-links',
  './assets/modules/deck-data/ui.js?v=20261117-data-folder-2',
  './assets/modules/deck-data/styles.css?v=20261117-data-1',
  './assets/modules/play-calculator/card-menu.js?v=20261116-actions-1',
  './assets/modules/play-calculator/card-menu.css?v=20261116-actions-1',
  './assets/modules/play-calculator/workspace.js?v=20261116-actions-1',
  './assets/modules/play-calculator/workspace.css?v=20261116-workspace-1',
  './assets/modules/card-inspector/model.js?v=20261116-readable-3',
  './assets/modules/card-inspector/ui.js?v=20261116-readable-3',
  './assets/modules/card-inspector/styles.css?v=20261116-readable-3',
  './assets/modules/deck-attributes/model.js?v=20261118-worlds-hexagonal',
  './assets/modules/deck-attributes/api.js?v=20261118-worlds-hexagonal',
  './assets/modules/deck-attributes/ui.js?v=20261118-worlds-hexagonal',
  './assets/modules/deck-attributes/style.css?v=20261118-worlds-hexagonal',
  './assets/modules/deck-guide/league-position.css?v=20261113-match-positions-183',
  './assets/modules/statistics/live.js?v=20261113-stats-live-180',
  './assets/modules/deck-guide/league-position.js?v=20261113-match-positions-182',
  './assets/modules/statistics/global-ranking.js?v=20261117-global-catalog',
  './assets/modules/statistics/workspace.js?v=20261117-global-catalog',
  './assets/modules/statistics/workspace.css?v=20261117-global-catalog',
  './assets/modules/combo-creator/step-lesson.js?v=20261113-step-lessons-173',
  './assets/modules/combo-creator/step-lesson.css?v=20261113-step-lessons-173',
  './assets/modules/deck-guide/list-metadata.css?v=20261113-deck-metadata-172',
  './assets/modules/play-calculator/hand-practice.js?v=20261113-hand-practice-170',
  './assets/modules/combo-creator/documented-import.js?v=20261116-folder-import-1',
  './assets/modules/navigation/global-search.css?v=20261113-global-search-166',
  './assets/modules/navigation/global-search.js?v=20261113-global-search-167',
  './assets/modules/navigation/sidebar.css?v=20261117-sidebar-arrow',
  './assets/modules/cup-bracket/navigation.js?v=20261108-cup-open-155',
  './assets/modules/cup-bracket/navigation.css?v=20261108-cup-open-155',
  './assets/modules/play-calculator/rules.js?v=20261118-block1-4',
  './assets/modules/play-calculator/chain.js?v=20261118-chain-2',
  './assets/modules/play-calculator/verified-rules.js?v=20261118-memento-effects-2',
  './assets/modules/play-calculator/study.js?v=20261118-scenario-1',
  './assets/modules/play-calculator/scenario.js?v=20261118-scenario-1',
  './assets/modules/play-calculator/battle.js?v=20261118-battle-1',
  './assets/modules/play-calculator/planner.js?v=20261118-block1-2',
  './assets/modules/play-calculator/planner-worker.js?v=20261118-memento-effects-2',
  './assets/modules/play-calculator/card-facts.json?v=20261108-planner-152',
  './assets/modules/deck-guide/photo-viewer.js?v=20261108-reader-148',
  './assets/modules/dashboard/ranking-scopes.js?v=20261107-ranking-divisions-146',
  './assets/modules/deck-guide/endboard.css?v=20261113-combo-library-164',
  './assets/modules/deck-guide/endboard.js?v=20261113-combo-library-164',
  './assets/modules/deck-guide/curated-visual.css?v=20261113-combo-library-164',
  './assets/modules/deck-guide/delivery-extensions.js?v=20261116-folder-import-1',
  './assets/modules/deck-guide/delivery-extensions.css?v=20261114-delivery-v12-186',
  './assets/modules/deck-guide/curated-visual.js?v=20261114-delivery-v12-186',
  './assets/modules/deck-guide/curated-study.css?v=20261113-combo-library-164',
  './assets/modules/deck-guide/functional-role.js?v=20261116-folder-import-1',
  './assets/modules/deck-guide/card-detail.js?v=20261115-card-detail-196',
  './assets/modules/deck-guide/curated-study.js?v=20261117-folder-delivery-v37-complete',
  './assets/modules/combo-creator/editorial-library.js?v=20261113-combo-library-164',
  './assets/modules/deck-guide/study-eleven.js?v=20261107-study-ten-143',
  './assets/modules/deck-guide/study-sections.css?v=20261107-study-eleven-139',
  './assets/modules/deck-guide/study-sections.js?v=20261107-study-ten-143',
  './assets/modules/deck-guide/checked-study.js?v=20261108-curated-147',
  './assets/modules/deck-guide/checked-study.css?v=20261104-checked-study-137',
  './assets/modules/deck-guide/content-state.js?v=20261113-combo-library-164',
  './assets/modules/match-sheet/layout.css?v=20261104-match-sheet-134',
  './assets/modules/match-sheet/navigation.js?v=20261104-match-sheet-134',
  './assets/cards/originals/identities.json?v=20261103-combos-130',
  './assets/modules/combo-creator/model.js?v=20261103-combos-133',
  './assets/modules/combo-creator/ui.js?v=20261116-deck-picker-1',
  './assets/modules/combo-creator/styles.css?v=20261116-deck-picker-1',
  './assets/cards/originals/index.json?v=20261103-board-129',
  './assets/modules/deck-export/export.js?v=20261102-export-127',
  './play-calculator.html',
  './assets/modules/play-calculator/offline.js?v=20261103-board-129',
  './assets/modules/play-calculator/engine.js?v=20261116-actions-1',
  './assets/modules/play-calculator/storage.js?v=20261103-board-129',
  './assets/modules/play-calculator/ui.js?v=20261118-memento-effects-2',
  './assets/modules/play-calculator/styles.css?v=20261118-scenario-1',
  './assets/modules/deck-guide/deck-study.js?v=20261102-library-125',
  './assets/modules/deck-guide/deck-deliveries.js?v=20261102-library-125',
  './assets/editorial/deck-deliveries.json?v=20261101-11-decks-123',
  './assets/modules/deck-guide/learning-guide.js?v=20261102-library-125',
  './assets/modules/deck-guide/learning-guide.css?v=20261102-library-125',
  './assets/guides/learning-guides.json?v=20261030-guide-120',
  './',
  './?offline=1#resultados',
  './assets/app.css?v=20261024-event-filters-90',
  './assets/library.css?v=20261018-library-48',
  './assets/library-search.js?v=20261018-library-48',
  './assets/library.js?v=20261018-library-48',
  './assets/match-editor.js?v=20261027-participants-103',
  './assets/match-editor.css?v=20261020-match-49',
  './assets/free-for-all.js?v=20261018-ffa-47',
  './assets/event-admission.js?v=20261018-ranking-47',
  './assets/event-recovery.js?v=20261018-ranking-47',
  './assets/event-teams.js?v=20261018-ranking-47',
  './assets/event-models.js?v=20261018-ranking-47',
  './assets/statistics-v8.js?v=20261117-global-catalog',
  './assets/event-audit.js?v=20261021-audit-55',
  './assets/cup-tree.js?v=20261108-cup-open-155',
  './assets/deck-events.js?v=20261024-event-filters-90',
  './assets/daily-duels.js?v=20261026-labels-101',
  './assets/ui-labels.js?v=20261026-labels-101',
  './assets/card-inspector.js?v=20261028-inspector-111',
  './assets/deck-builder.js?v=20261028-inspector-111',
  './assets/deck-builder.css?v=20261028-inspector-111',
  './assets/deck-history.js?v=20261024-history-92',
  './assets/deck-history.css?v=20261024-history-92',
  './assets/modules/deck-guide/strategy-workspace.js?v=20261030-depth-122',
  './assets/modules/deck-guide/strategy-workspace.css?v=20261024-strategy-95',
  './assets/editorial/station_x_perfiles_67_decks.txt?v=20261030-profiles-119',
  './assets/result-context.css?v=20261026-divisions-100',
  './assets/participant-labels.js?v=20261027-participants-103',
  './assets/result-desktop.css?v=20261109-result-button-158',
  './assets/result-desktop.js?v=20261109-result-button-158',
  './assets/lp-workspace.css?v=20261027-lp-105',
  './assets/lp-workspace.js?v=20261027-lp-105',
  './assets/lp-sound.js?v=20261027-sound-106',
  './assets/alert-colors.css?v=20261109-alert-colors-156',
  './assets/alert-order.css?v=20261027-alert-order-109',
  './assets/storage-maintenance.js?v=20261028-cleanup-113',
  './assets/news.css?v=20261028-news-116',
  './assets/news.js?v=20261028-news-115',
  './assets/app.js?v=20261117-match-entities',
  './assets/seasons.js?v=20261016-season-editor-4',
  './manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).then(() => self.skipWaiting()));
});

// Separate expendable files from the installed files needed to open offline.
const RUNTIME_CACHE='yugioh-md-runtime-v1';
const MAINTENANCE_CACHE='yugioh-md-maintenance-v1';
const MAINTENANCE_URL=new URL('__storage_cleanup_metadata__',self.registration.scope).href;
const MAX_RUNTIME_ENTRIES=150,MAX_RUNTIME_AGE=7*86400000;
const shellUrls=new Set(APP_SHELL.map(path=>{const u=new URL(path,self.registration.scope);u.hash='';return u.href;}));
const colombiaDay=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'America/Bogota',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
let cleanupJob=null;
function cacheTime(response){const at=Date.parse(response?.headers.get('date')||'');return Number.isFinite(at)?at:0;}
async function trimRuntime(){
  const cache=await caches.open(RUNTIME_CACHE),entries=[],now=Date.now();let removed=0;
  for(const key of await cache.keys()){
    const response=await cache.match(key),at=cacheTime(response);
    if(!at||now-at>MAX_RUNTIME_AGE){if(await cache.delete(key))removed++;}
    else entries.push({key,at});
  }
  entries.sort((a,b)=>a.at-b.at||a.key.url.localeCompare(b.key.url));
  for(const entry of entries.slice(0,Math.max(0,entries.length-MAX_RUNTIME_ENTRIES)))if(await cache.delete(entry.key))removed++;
  return removed;
}
async function limitRuntime(){const cache=await caches.open(RUNTIME_CACHE);if((await cache.keys()).length>MAX_RUNTIME_ENTRIES)await trimRuntime();}
function dailyCleanup(force=false){
  if(cleanupJob)return cleanupJob;
  cleanupJob=(async()=>{
    const meta=await caches.open(MAINTENANCE_CACHE),previous=await meta.match(MAINTENANCE_URL);
    let last=null;try{last=await previous?.json();}catch(_){}
    const day=colombiaDay();if(!force&&last?.day===day)return last;
    let removedCaches=0;
    for(const name of await caches.keys())if(/^yugioh-md-match-v\d+$/.test(name)&&name!==CACHE_NAME){if(await caches.delete(name))removedCaches++;}
    const result={day,at:Date.now(),removedCaches,removedFiles:await trimRuntime()};
    await meta.put(MAINTENANCE_URL,new Response(JSON.stringify(result),{headers:{'Content-Type':'application/json'}}));return result;
  })().finally(()=>{cleanupJob=null;});
  return cleanupJob;
}
self.addEventListener('activate',event=>{
  event.waitUntil((async()=>{await dailyCleanup(true);await self.clients.claim();})());
});
self.addEventListener('message',event=>{
  if(event.data?.type!=='STATIONX_DAILY_STORAGE_CLEANUP')return;
  event.waitUntil(dailyCleanup().then(result=>event.ports[0]?.postMessage({ok:true,...result})).catch(()=>event.ports[0]?.postMessage({ok:false})));
});
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return;
  const url=new URL(event.request.url),scope=new URL(self.registration.scope);
  if(url.origin!==scope.origin||!url.pathname.startsWith(scope.pathname))return;
  // Live data and downloads always go to the server; never retain report copies.
  if(url.searchParams.has('api')||url.searchParams.has('export'))return;
  const core=shellUrls.has(url.href),asset=url.pathname.startsWith(scope.pathname+'assets/');
  if(!core&&!asset&&event.request.mode!=='navigate')return;
  let cacheWork=Promise.resolve();
  const response=fetch(event.request).then(response=>{
    if(response.ok&&(core||asset)&&!response.headers.get('vary')?.split(',').some(v=>v.trim()==='*')){
      const copy=response.clone();
      cacheWork=caches.open(core?CACHE_NAME:RUNTIME_CACHE).then(cache=>cache.put(event.request,copy)).then(()=>core?undefined:limitRuntime()).catch(()=>{});
    }
    return response;
  }).catch(async()=>{
    const cache=await caches.open(core?CACHE_NAME:RUNTIME_CACHE),saved=await cache.match(event.request);
    if(saved&&(core||Date.now()-cacheTime(saved)<=MAX_RUNTIME_AGE))return saved;
    if(saved&&!core)await cache.delete(event.request);
    if(event.request.mode==='navigate'){
      const shell=await caches.open(CACHE_NAME);if(url.pathname===new URL('./play-calculator.html',scope).pathname)return await shell.match(new URL('./play-calculator.html',scope).href)||Response.error();return await shell.match(new URL('./?offline=1',scope).href)||Response.error();
    }
    return Response.error();
  });
  event.respondWith(response);
  event.waitUntil(response.then(()=>cacheWork).catch(()=>{}));
});
