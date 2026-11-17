(function(root,factory){
  const api=factory();
  if(typeof module==='object'&&module.exports)module.exports=api;
  else root.YgoDashboardRankingScopes=api;
})(typeof window==='undefined'?globalThis:window,function(){
  'use strict';
  const same=(a,b)=>String(a??'')===String(b??'');
  function options(data){
    const divisions=Object.entries(data.divisions||{});
    return (data.competitions||[]).filter(c=>['active','activo','en_curso'].includes(String(c.status||'').toLowerCase().replace(/\s+/g,'_'))).flatMap(competition=>{
      const ids=new Set((competition.division_ids||[]).map(String));
      if(Number(competition.division_id)>0)ids.add(String(competition.division_id));
      const attached=divisions.filter(([,d])=>Number(d.id)>0&&(competition.type==='league'
        ? same(d.league_competition_id,competition.id)||ids.has(String(d.id))
        : ids.has(String(d.id))));
      if(!attached.length)return [{value:String(competition.id),competitionId:competition.id,label:competition.name||'Evento activo',divisionId:null}];
      return attached.sort((a,b)=>Number(a[1].tier_order||0)-Number(b[1].tier_order||0)||Number(a[1].id)-Number(b[1].id)).map(([code,d])=>{
        const title=String(d.name||d.short||code).split(/\s+[—–]\s+/).at(-1);
        const deckIds=new Set((d.decks||[]).map(deck=>String(deck.id||deck.deck_id)));
        (data.allDecks||[]).filter(deck=>same(deck.division_id,d.id)).forEach(deck=>deckIds.add(String(deck.id)));
        return {value:`${competition.id}:${d.id}`,competitionId:competition.id,divisionId:d.id,code,
          label:`${competition.name||'Evento activo'} — ${title}`,deckIds:[...deckIds]};
      });
    });
  }
  function matches(scope,rows){
    return rows.filter(m=>same(m.competition_id,scope.competitionId)&&(!scope.divisionId||
      (Number(m.division_id)>0?same(m.division_id,scope.divisionId):same(m.division,scope.code))));
  }
  function ranking(scope,rows){
    if(!scope.divisionId)return rows;
    const ids=new Set(scope.deckIds);
    return rows.filter(row=>Number(row.division_id)>0?same(row.division_id,scope.divisionId):ids.has(String(row.id||row.deck_id)));
  }
  return {options,matches,ranking};
});
