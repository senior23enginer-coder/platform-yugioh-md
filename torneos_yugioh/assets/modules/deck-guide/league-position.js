(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.YgoDeckLeaguePosition = api;
})(typeof globalThis === 'undefined' ? window : globalThis, function () {
  'use strict';
  function resolve(data, deck, league, division) {
    const empty = {position: null, total: 0, label: 'Sin liga', reason: 'No tiene liga activa en su división.'};
    if (!league || !division || league.type !== 'league' || Number(league.active ?? 1) === 0 ||
        ['completed', 'finished', 'cancelled', 'archived'].includes(league.status) ||
        String(deck.division_id) !== String(division.id) ||
        String(division.league_competition_id) !== String(league.id)) return empty;
    const byId = new Map((data.allDecks || []).map(d => [String(d.id), d]));
    // Reuse the server ranking order. Do not sort again or use global rating/seed as a position.
    const rows = (league.ranking || []).filter(row => {
      const id = row.id || row.deck_id, own = byId.get(String(id));
      return String(row.division_id || own?.division_id || '') === String(division.id);
    });
    const pending = {...empty, label: 'Sin clasificación', total: rows.length, reason: 'No hay clasificación con resultados para esta división.'};
    if (!rows.some(r => Number(r.pj ?? r.played ?? r.matches ?? 0) > 0)) return pending;
    const index = rows.findIndex(r => String(r.id || r.deck_id) === String(deck.id));
    if (index < 0) return {...pending, reason: 'El deck no aparece en la clasificación de esta liga y división.'};
    return {position: index + 1, total: rows.length, label: `${index + 1}.º de ${rows.length}`, reason: `${league.name} · ${division.name || division.short || ''}`};
  }
  function forMatch(data, match) {
    const league=(data.competitions||[]).find(c=>String(c.id)===String(match.competition_id));
    if(league?.type!=='league')return null;
    const divisions=Object.values(data.divisions||{});
    const division=Number(match.division_id)>0 ? divisions.find(d=>String(d.id)===String(match.division_id)) : data.divisions?.[match.division];
    const divisionId=division?.id || (Number(match.division_id)>0 ? match.division_id : null);
    const byId=new Map((data.allDecks||[]).map(d=>[String(d.id),d]));
    const rows=divisionId ? (league.ranking||[]).filter(r=>String(r.division_id||byId.get(String(r.id||r.deck_id))?.division_id||'')===String(divisionId)) : [];
    const played=rows.some(r=>Number(r.pj??r.played??r.matches??0)>0);
    const participant=id=>{
      const deck=byId.get(String(id)),index=rows.findIndex(r=>String(r.id||r.deck_id)===String(id));
      return {deck_id:id,name:deck?.name||'Participante',position:played&&index>=0?index+1:null,total:rows.length,label:played&&index>=0?`${index+1}.º de ${rows.length}`:'Sin clasificación'};
    };
    const side=keys=>[...new Set(keys.map(k=>match[k]).filter(id=>Number(id)>0))].map(participant);
    return {league_id:league.id,division_id:divisionId,a:side(['deck_a_id','deck_c_id','deck_e_id']),b:side(['deck_b_id','deck_d_id','deck_f_id'])};
  }
  return {resolve, forMatch};
});
