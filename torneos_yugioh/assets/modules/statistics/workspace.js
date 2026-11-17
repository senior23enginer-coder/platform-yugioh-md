(function (root) {
  'use strict';
  const tabs = [['format', 'Ranking por formato'], ['global', 'Ranking global'], ['meta', 'Metagame'], ['tables', 'Tablas']];
  const labels = {tcg_advanced: 'TCG', goat: 'GOAT', goat_2005: 'GOAT', rush_duel: 'Rush Duel'};
  let pendingTab = 'format', current = null;
  function open(tab) {
    if (!tabs.some(([key]) => key === tab)) return;
    pendingTab = tab;
    current?.select(tab);
  }
  async function mount({source, escape: esc, num, table, name}) {
    const screen = document.querySelector('[data-screen="estadisticas"]');
    if (!screen || screen.dataset.statsWorkspace === 'mounted') return;
    screen.dataset.statsWorkspace = 'mounted';
    const standingPanel = screen.querySelector('[data-stats-tables-panel]');
    let data = source().statisticsV8 || {}, active = pendingTab, sequence = 0;
    const formats = [...new Set([
      ...(source().competitions || []).map(c => c.event_ruleset),
      ...(data.global || []).map(r => r.format_id),
      ...(data.metagame || []).map(r => r.format_id)
    ].filter(Boolean))];
    formats.sort((a, b) => (a === 'tcg_advanced' ? -1 : b === 'tcg_advanced' ? 1 : a.localeCompare(b)));
    const formatName = f => labels[f] || f;
    const selections = {format: formats[0] || '', global: '', meta: formats[0] || ''};
    const publicSeason = new URLSearchParams(location.search).get('public_season');
    let season = String(publicSeason || source().currentSeasonId || source().seasons?.[0]?.id || '');
    const banner = screen.querySelector('.public-season-banner');
    screen.innerHTML = `<header class="page-title"><h1>Estadísticas y tops</h1><p>Rankings, metagame y tablas de clasificación.</p></header><nav class="stats-workspace-tabs" role="tablist" aria-label="Estadísticas y tops">${tabs.map(([key, title]) => `<button type="button" id="stats-tab-${key}" role="tab" data-v8-tab="${key}" aria-controls="stats-workspace-content">${title}</button>`).join('')}</nav><section class="panel stats-workspace-controls"><label data-stats-format-control>Formato<select class="form-select" data-v8-format></select></label><label data-stats-season-control>Temporada<select class="form-select" data-v8-meta-season>${(source().seasons || []).map(s => `<option value="${esc(s.id)}">${esc(s.name)}</option>`).join('')}</select></label></section><section id="stats-workspace-content" role="tabpanel" tabindex="0"><p data-v8-context class="note" aria-live="polite"></p><div class="panel" data-v8-body></div></section>`;
    if (banner) screen.prepend(banner);
    const controls = screen.querySelector('.stats-workspace-controls');
    const panel = screen.querySelector('#stats-workspace-content');
    if (standingPanel) panel.append(standingPanel);
    const body = screen.querySelector('[data-v8-body]'), context = screen.querySelector('[data-v8-context]');
    const progress = r => r.state === 'INACTIVO' ? 'Inactivo' : r.state === 'REACTIVACION PENDIENTE' ? 'Pendiente de reactivación' : `En evaluación: ${r.J || 0}/${data.policy?.minimum_matches || 2} partidas`;
    const columns = [
      ['Posición', r => r.position == null ? '—' : num(r.position)],
      ['Equipo / deck', r => `${r.team_id ? esc(name(r.team_id)) : 'Sin equipo'} / ${esc(name(r.deck_id, 'deck'))}${r.state !== 'CLASIFICADO' ? `<small class="d-block">${esc(progress(r))}</small>` : ''}`],
      ['Puntuación', r => `<span title="${esc('Elo: '+r.rating+' · Bonos de campeonato: '+(r.bonus_medallas || 0))}">${num(r.total)}</span>`],
      ['Partidas', r => num(r.J)]
    ];
    async function render() {
      const request = ++sequence, tab = active, format = selections[tab];
      screen.querySelectorAll('[data-v8-tab]').forEach(b => {
        const selected = b.dataset.v8Tab === tab;
        b.classList.toggle('active', selected);b.setAttribute('aria-selected', String(selected));b.tabIndex = selected ? 0 : -1;
      });
      panel.setAttribute('aria-labelledby', `stats-tab-${tab}`);
      controls.hidden = tab === 'tables' || tab === 'global';body.hidden = tab === 'tables';context.hidden = tab === 'tables';
      if (standingPanel) standingPanel.hidden = tab !== 'tables';
      screen.querySelector('[data-stats-season-control]').hidden = tab !== 'meta';
      screen.querySelector('[data-stats-format-control]').hidden = tab === 'global';
      if (tab === 'tables') return;
      const formatSelect = screen.querySelector('[data-v8-format]');
      formatSelect.innerHTML = (tab === 'global' ? '<option value="">Todos los formatos · separados</option>' : '') + formats.map(f => `<option value="${esc(f)}">${esc(formatName(f))}</option>`).join('');
      formatSelect.value = format;screen.querySelector('[data-v8-meta-season]').value = season;
      context.textContent = `Actualización automática con resultados confirmados · Última actualización: ${data.cutoff || 'Sin datos'}`;
      body.innerHTML = '<p role="status">Cargando clasificación…</p>';
      if (tab === 'global') {
        const pool = root.YgoGlobalRanking.build(source());
        body.innerHTML = `<h2>Ranking global TCG/OCG</h2><p>${pool.total} decks de todas las ligas y divisiones. Excluye Rush y GOAT. Puesto desde 2 partidas confirmadas; los decks sin partidas también forman parte del listado.</p><section data-stats-format="tcg_ocg"><h3>TCG / OCG</h3><h4>Clasificados</h4>${table(pool.rows.filter(row => row.position != null), columns)}<h4>En evaluación y fuera de clasificación</h4>${table(pool.rows.filter(row => row.position == null), columns)}</section>`;
        return;
      }
      const wanted = tab === 'global' && !format ? formats : [format].filter(Boolean);
      if (!wanted.length) {body.innerHTML = '<p>No hay formatos con datos para consultar.</p>'; return;}
      try {
        const views = wanted.map(f => ({format: f, data}));
        if (request !== sequence) return;
        const title = tab === 'meta' ? 'Metagame' : tab === 'global' ? 'Ranking global' : 'Ranking por formato';
        const intro = tab === 'meta'
          ? 'Clasificación estacional por resultados y calidad de muestra. Las evaluaciones editoriales no asignan tiers deportivos.'
          : tab === 'global'
            ? 'La puntuación considera la fuerza del rival. Puesto desde 2 partidas confirmadas; cada formato tiene su clasificación.'
            : 'Puesto desde 2 partidas confirmadas. La puntuación considera la fuerza del rival y conserva los bonos de campeonato vigentes.';
        body.innerHTML = `<h2>${title}</h2><p>${intro}</p>` + views.map(v => {
          const heading = `<h3>${esc(formatName(v.format))}</h3>`;
          if (tab === 'meta') return `<section data-stats-format="${esc(v.format)}">${heading}${table((v.data.metagame || []).filter(r => String(r.season_id) === season && r.format_id === v.format), [['Deck', r => esc(name(r.deck_id, 'deck'))], ['Tier', r => esc(r.tier)], ['IC', r => num(r.IC)], ['Elo estacional', r => num(r.rating_season)], ['WR %', r => num(r.match_WR)], ['Partidas', r => num(r.J)], ['Calidad', r => esc(r.quality_status)]])}</section>`;
          const rows = (v.data.global || []).filter(r => r.format_id === v.format);
          return `<section data-stats-format="${esc(v.format)}">${heading}<h4>Clasificados</h4>${table(rows.filter(r => r.state === 'CLASIFICADO'), columns)}<h4>En evaluación y fuera de clasificación</h4>${table(rows.filter(r => r.state !== 'CLASIFICADO'), columns)}</section>`;
        }).join('');
      } catch (error) {
        if (request === sequence) body.innerHTML = `<p role="alert">${esc(error.message)} Vuelve a intentarlo.</p>`;
      }
    }
    const select = tab => {active = tab;pendingTab = tab;return render();};current = {select};
    screen.querySelectorAll('[data-v8-tab]').forEach((button, i, buttons) => {
      button.addEventListener('click', () => select(button.dataset.v8Tab));
      button.addEventListener('keydown', e => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
        e.preventDefault();const next = e.key === 'Home' ? 0 : e.key === 'End' ? buttons.length - 1 : (i + (e.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
        buttons[next].focus();select(buttons[next].dataset.v8Tab);
      });
    });
    screen.querySelector('[data-v8-format]').addEventListener('change', e => {selections[active] = e.target.value;render();});
    screen.querySelector('[data-v8-meta-season]').addEventListener('change', e => {season = e.target.value;render();});
    root.addEventListener('ygo:statistics-updated',()=>{data=source().statisticsV8||data;render();});
    root.addEventListener('ygo:statistics-status',e=>{if(!e.detail.ok)context.textContent='No se pudo actualizar. Se muestran los últimos datos recibidos.';});
    await render();
    root.YgoStatisticsLive?.refresh();
  }
  root.YgoStatisticsWorkspace = {mount, open};
})(window);
