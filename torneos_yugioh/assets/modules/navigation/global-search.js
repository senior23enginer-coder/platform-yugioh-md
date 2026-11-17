(function(root){
  'use strict';
  const norm=v=>String(v??'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase().replace(/\s+/g,' ').trim();
  const joined=values=>values.filter(v=>v!==null&&v!==undefined&&v!=='').join(' · ');
  function search(data,query,limit=12){
    const q=norm(query);if(!q)return [];
    const decks=data.allDecks||[],teams=data.teams||[];
    const eventTypes={league:'Liga',cup:'Copa',tournament:'Torneo',qualifier:'Clasificatorio',world:'Mundial',final:'Final'};
    const entries=[
      ...decks.map(d=>({type:'Deck',id:d.id,label:d.name,description:joined([d.team||'Sin equipo',d.division,d.archetype]),extra:joined([d.archetype,d.team,d.division,...(d.format_codes||[])])})),
      ...teams.map(t=>({type:'Equipo',id:t.id,label:t.name,code:t.short_code,description:joined([t.short_code,t.city]),extra:joined([t.short_code,t.city,...decks.filter(d=>String(d.team_id)===String(t.id)).map(d=>d.name)])})),
      ...(data.competitions||[]).map(e=>({type:'Evento',id:e.id,label:e.display_name||e.name,code:e.code,description:joined([eventTypes[e.type]||e.type,e.format||e.format_code,e.status]),extra:joined([e.name,e.code,e.type,eventTypes[e.type],e.format,e.format_code,e.division_code])}))
    ];
    const seen=new Set(),tokens=q.split(' ');
    return entries.filter(e=>{
      if(!e.id||!e.label)return false;const key=e.type+':'+e.id;if(seen.has(key))return false;seen.add(key);
      return tokens.every(t=>norm(e.label+' '+e.extra).includes(t));
    }).map(e=>{const label=norm(e.label);return {...e,rank:label===q?0:norm(e.code)===q?1:label.startsWith(q)?2:label.includes(q)?3:tokens.every(t=>label.includes(t))?4:5};})
      .sort((a,b)=>a.rank-b.rank||a.label.localeCompare(b.label,'es',{sensitivity:'base'})||String(a.id).localeCompare(String(b.id)))
      .slice(0,limit).map(({rank,extra,code,...e})=>e);
  }
  root.YgoGlobalSearch={search,norm};
  if(typeof module==='object'&&module.exports)module.exports=root.YgoGlobalSearch;
})(typeof window!=='undefined'?window:globalThis);
