(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.YgoLibrarySearch=api;})(typeof window==='object'?window:globalThis,function(){
  'use strict';
  const normalize=v=>String(v??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[_/–—-]/g,' ').replace(/[^a-z0-9\s]/g,' ').replace(/\s+/g,' ').trim();
  const stop=new Set('a al algo ante con como cual cuales cuando de del desde donde el ella en entre es esa ese esto esta estas este estos ha hay la las lo los me mi nos o para pero por porque que quien se si sin sobre son su sus te tiene todo tu un una unos unas y yo quiero saber consultar consulta informacion explica explicame significa funciona funcionamiento puedo puedes podria deberia'.split(' '));
  const stem=s=>s.length>6&&s.endsWith('iones')?s.slice(0,-2):s.length>4&&s.endsWith('es')?s.slice(0,-2):s.length>3&&s.endsWith('s')?s.slice(0,-1):s;
  const tokens=s=>[...new Set(normalize(s).split(' ').filter(w=>w&&!stop.has(w)).map(stem))];
  const synonymSets=[['ida vuelta','double round robin','doble vuelta','doble round robin'],['todos contra todos','round robin'],['suizo','swiss'],['eliminacion directa','knockout','ko'],['battle royale','battle royal','free for all','ffa','multijugador'],['rey colina','king of the hill'],['cupos','plazas','clasificados'],['modelo','plantilla','preconfigurado'],['deck','mazo','baraja'],['ranking','clasificacion','rating','elo'],['bye','descanso'],['tag duel','tag','2v2'],['equipo tres','3v3'],['terminologia','glosario','definicion'],['crear','configurar','configuro','creacion'],['cierre','finalizar','cerrar']].map(group=>group.map(normalize));
  function distance(a,b,limit){if(Math.abs(a.length-b.length)>limit)return limit+1;let prev=Array.from({length:b.length+1},(_,i)=>i);for(let i=1;i<=a.length;i++){const row=[i];let low=i;for(let j=1;j<=b.length;j++){row[j]=Math.min(row[j-1]+1,prev[j]+1,prev[j-1]+(a[i-1]===b[j-1]?0:1));low=Math.min(low,row[j]);}if(low>limit)return limit+1;prev=row;}return prev[b.length];}
  function createIndex(articles){
    const vocabulary=new Set(),rows=articles.map((article,order)=>{const fields=[article.title,[article.category,...(article.aliases||[])].join(' '),article.summary,(article.sections||[]).flatMap(s=>[s.title,...s.paragraphs]).join(' ')],weights=[16,12,6,1],map=new Map();fields.forEach((field,i)=>{for(const word of tokens(field)){map.set(word,(map.get(word)||0)+weights[i]);vocabulary.add(word);}});return {article,order,map,fields:fields.map(normalize)};});
    return {rows,vocabulary:[...vocabulary]};
  }
  function search(index,query='',category=''){
    const text=normalize(query).slice(0,500),words=tokens(text).slice(0,24),expanded=[];
    for(const group of synonymSets)if(group.some(phrase=>(' '+text+' ').includes(' '+phrase+' ')))expanded.push(...group.flatMap(tokens));
    const alternates=new Set(expanded),cache=new Map();
    const variants=word=>{if(cache.has(word))return cache.get(word);const matches=[];for(const term of index.vocabulary){if(term===word)matches.push([term,1]);else if(word.length>=3&&term.startsWith(word))matches.push([term,.65]);else if(word.length>=4&&distance(word,term,word.length>=8?2:1)<=(word.length>=8?2:1))matches.push([term,.38]);}cache.set(word,matches);return matches;};
    const results=[];
    for(const row of index.rows){if(category&&row.article.category!==category)continue;
      if(!words.length){if(!text)results.push({article:row.article,score:0,order:row.order,matched:[],reason:'Explorar la biblioteca'});continue;}
      let score=0,hits=0;const matched=[];
      for(const word of words){let best=0,used='';for(const [term,factor] of variants(word)){const value=(row.map.get(term)||0)*factor;if(value>best){best=value;used=term;}}if(best){hits++;score+=best;matched.push(used);}}
      let synonymScore=0;for(const word of alternates)synonymScore+=Math.min(12,row.map.get(word)||0)*.35;
      const exact=row.fields.findIndex(field=>field.includes(text));if(exact>=0)score+=[60,50,25,8][exact];
      if(hits<Math.max(1,Math.ceil(words.length*.6))&&!(words.length===1&&synonymScore>=3))continue;
      score+=synonymScore+15*(hits/words.length);results.push({article:row.article,score,order:row.order,matched,reason:exact===0?'Coincide con el título':exact===1?'Coincide con un nombre o código':hits===words.length?'Coincide con tu consulta':'Coincidencia aproximada o por términos relacionados'});
    }
    return results.sort((a,b)=>b.score-a.score||a.order-b.order);
  }
  return {normalize,tokens,createIndex,search};
});
