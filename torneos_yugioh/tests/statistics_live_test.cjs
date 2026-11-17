const vm=require('node:vm'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const events=[],listeners={},docListeners={},timers=[];let calls=0,hidden=false;
const window={DML_DATA:{statisticsV8:{global:[],cutoff:'old'}},addEventListener(k,fn){listeners[k]=fn;},dispatchEvent(e){events.push(e);},setInterval(fn,ms){timers.push({fn,ms});}};
const document={get hidden(){return hidden;},querySelector(){return {hidden:false};},addEventListener(k,fn){docListeners[k]=fn;}};
let payload={global:[{team_id:1,position:1}],cutoff:'new'},fail=false;
const sandbox={window,document,CustomEvent:class{constructor(type,options={}){this.type=type;this.detail=options.detail;}},fetch:async(url,options)=>{calls++;assert.equal(url,'?api=statistics');assert.equal(options.cache,'no-store');return {ok:!fail,json:async()=>payload};}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/modules/statistics/live.js'),'utf8'),sandbox);
(async()=>{
 await Promise.all([window.YgoStatisticsLive.refresh(),window.YgoStatisticsLive.refresh()]);assert.equal(calls,1);assert.equal(window.DML_DATA.statisticsV8.cutoff,'new');assert.equal(events.filter(e=>e.type==='ygo:statistics-updated').length,1);
 await window.YgoStatisticsLive.refresh();assert.equal(events.filter(e=>e.type==='ygo:statistics-updated').length,1);
 payload={global:[{team_id:1,position:2}],cutoff:'newer'};await listeners.focus();assert.equal(window.DML_DATA.statisticsV8.global[0].position,2);
 hidden=true;const before=calls;await window.YgoStatisticsLive.refresh();assert.equal(calls,before);hidden=false;
 fail=true;await window.YgoStatisticsLive.refresh();assert.equal(window.DML_DATA.statisticsV8.cutoff,'newer');assert.equal(events.at(-1).detail.ok,false);
 docListeners.DOMContentLoaded();assert.equal(timers[0].ms,30000);
 console.log('OK: automatic refresh, changed-result projection, request deduplication, hidden tab, retained data on network error.');
})().catch(e=>{console.error(e);process.exitCode=1;});
