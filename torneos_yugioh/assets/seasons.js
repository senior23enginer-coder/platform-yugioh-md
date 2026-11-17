(() => {
  const data = window.DML_DATA || {};
  const seasons = data.seasons || [];
  const modalNode = document.querySelector('#seasonEditorModal');
  const form = modalNode?.querySelector('[data-season-form]');
  const modal = modalNode && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalNode) : null;
  const field = name => form?.elements.namedItem(name);
  const months = { annual: 12, semiannual: 6, quarterly: 3 };
  function updateEnd() {
    const start = field('season_starts_on')?.value;
    if (!start) { field('season_ends_on').value = ''; return; }
    const [year, month, day] = start.split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1 + months[field('season_period').value], 1));
    const last = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
    target.setUTCDate(Math.min(day, last));
    target.setUTCDate(target.getUTCDate() - 1);
    field('season_ends_on').value = target.toISOString().slice(0, 10);
  }
  function openSeason(id, readOnly = false) {
    if (!form) return;
    const season = seasons.find(s => Number(s.id) === Number(id));
    form.reset();
    form.querySelectorAll('input:not([type="hidden"]),select').forEach(input => { input.disabled = readOnly; });
    field('season_id').value = season?.id || '0';
    field('season_name').value = season?.name || '';
    field('season_period').value = season?.period_type || 'annual';
    field('season_starts_on').value = season?.starts_on || `${new Date().getFullYear()}-01-01`;
    const status = field('season_status');
    status.querySelectorAll('[data-season-terminal]').forEach(option => option.remove());
    if (season && !['draft', 'active'].includes(season.status)) {
      const option = new Option(season.status === 'completed' ? 'Finalizada' : 'Archivada', season.status);
      option.dataset.seasonTerminal = '1'; status.add(option);
    }
    status.value = season?.status || 'draft';
    if (season?.ends_on) field('season_ends_on').value = season.ends_on; else updateEnd();
    field('season_ends_on').min = field('season_starts_on').value;
    renderSeasonEvents(season);
    form.querySelector('[data-season-finalize]').hidden = readOnly || !season || ['completed','archived'].includes(season.status);
    document.querySelector('#seasonEditorTitle').textContent = readOnly ? 'Consultar temporada' : (season ? 'Editar temporada' : 'Crear temporada');
    form.querySelector('[data-season-save]').hidden = readOnly;
    form.querySelector('[data-season-delete]').hidden = !season || Number(season.event_count) !== 0 || Number(season.division_count) !== 0;
    modalNode.querySelector('[data-season-delete-form] [name="season_id"]').value = season?.id || '0';
    const detail = form.querySelector('[data-season-detail]');
    detail.hidden = !season;
    if (season) detail.textContent = `${season.event_count || 0} eventos · ${season.division_count || 0} divisiones${season.closed_at ? ' · Cierre: ' + season.closed_at : ''}`;
    modal?.show();
  }
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  function renderSeasonEvents(season) {
    const panel = form.querySelector('[data-season-events]');
    panel.hidden = !season;
    const rows = (data.seasonEvents || []).filter(event => Number(event.season_id) === Number(season?.id));
    form.querySelector('[data-season-events-summary]').textContent = `${rows.length} eventos - ${season?.name || ''}`;
    const statusLabels = {pending_configuration:'Pendiente de configuracion',pending_event:'Pendiente',active:'Activo',completed:'Finalizado',closed:'Cerrado',cancelled:'Anulado',archived:'Archivado'};
    const types = data.competitionTypeOptions || {};
    form.querySelector('[data-season-events-rows]').innerHTML = rows.length ? rows.map(event => `<tr data-season-event-row="${Number(event.id)}"><td data-label="Evento"><strong>${escape(event.name)}</strong><small class="d-block">${escape(event.code)}</small></td><td data-label="Tipo">${escape(types[event.type]?.label || ({league:'Liga',cup:'Copa',tournament:'Torneo',special:'Evento especial',qualifier:'Clasificatorio',world:'Mundial',final:'Final'})[event.type] || event.type)}</td><td data-label="Estado">${escape(statusLabels[event.status] || event.status)}</td><td data-label="Inicio">${escape(event.starts_on || 'Sin definir')}</td><td data-label="Cierre programado">${escape(event.ends_on || 'Sin definir')}</td><td data-label="Cierre registrado">${escape(event.closed_at || 'Sin registro')}</td><td data-label="Acciones"><a class="btn btn-outline-info btn-sm" href="./?season_context=${Number(event.season_id)}&event_id=${Number(event.id)}#evento_ficha">Ver ficha</a></td></tr>`).join('') : '<tr><td colspan="7">Esta temporada no tiene eventos registrados.</td></tr>';
  }
  form?.addEventListener('submit', event => {
    if (event.submitter?.matches('[data-season-finalize]') && !window.confirm('?Guardar los ajustes y finalizar esta temporada? Se cerraran sus eventos y se conservara el historial.')) event.preventDefault();
  });
  document.querySelector('[data-season-new]')?.addEventListener('click', () => openSeason(0));
  document.querySelectorAll('[data-season-open]').forEach(button => button.addEventListener('click', () => {
    const season = seasons.find(s => Number(s.id) === Number(button.dataset.seasonOpen));
    if (season) openSeason(season.id, ['completed', 'archived'].includes(season.status));
  }));
  form?.querySelector('[data-season-delete]')?.addEventListener('click', () => modalNode.querySelector('[data-season-delete-form]').requestSubmit());
  field('season_period')?.addEventListener('change', updateEnd);
  field('season_starts_on')?.addEventListener('change', () => { updateEnd(); field('season_ends_on').min = field('season_starts_on').value; });
  function filterSeasons() {
    const text = (document.querySelector('[data-season-search]')?.value || '').trim().toLocaleLowerCase();
    const status = document.querySelector('[data-season-status-filter]')?.value || '';
    document.querySelectorAll('[data-season-row]').forEach(row => { row.hidden = !row.dataset.name.toLocaleLowerCase().includes(text) || Boolean(status && row.dataset.status !== status); });
  }
  document.querySelector('[data-season-search]')?.addEventListener('input', filterSeasons);
  document.querySelector('[data-season-status-filter]')?.addEventListener('change', filterSeasons);
  document.querySelectorAll('[data-season-close-form]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('¿Finalizar esta temporada y cerrar sus eventos? Se conservarán los resultados y el historial.')) event.preventDefault();
  }));
  document.querySelectorAll('[data-season-delete-form]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('¿Eliminar esta temporada vacía?')) event.preventDefault();
  }));
  function describeEventSeason(select) {
    const season = seasons.find(s => Number(s.id) === Number(select.value));
    const nameField = select.closest('label')?.querySelector('[data-event-season-name]');
    if (nameField) nameField.value = season?.name || '';
    const note = select.closest('label')?.querySelector('[data-event-season-description]');
    if (note) note.textContent = season ? `${season.starts_on} a ${season.ends_on}${['completed','archived'].includes(season.status) ? ' · Temporada finalizada: solo consulta.' : ''}` : 'Selecciona una temporada para el evento.';
    const form = select.closest('form');
    form?.querySelectorAll('[name="starts_on"],[name="ends_on"],[name="competition_start_on"]').forEach(input => {
      input.min = season?.starts_on || ''; input.max = season?.ends_on || '';
    });
  }
  document.querySelectorAll('[data-event-season-select]').forEach(select => {
    describeEventSeason(select);
    select.addEventListener('change', () => describeEventSeason(select));
  });
})();
