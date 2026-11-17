(() => {
  const node = document.getElementById('eventAuditModal');
  if (!node || !window.bootstrap) return;
  const modal = bootstrap.Modal.getOrCreateInstance(node);
  const rows = node.querySelector('[data-event-audit-rows]');
  const message = node.querySelector('[data-event-audit-message]');
  const search = node.querySelector('[data-event-audit-search]');
  const more = node.querySelector('[data-event-audit-more]');
  let eventId = '', cursor = null, request = 0;
  const labels = {
    divisions: 'Divisiones y cupos', dependencies: 'Prerrequisitos',
    mode: 'Operación', save: 'Guardar ficha', start: 'Iniciar evento', save_schedule: 'Guardar programación', schedule_cup: 'Programar copa', schedule_qualifier: 'Programar clasificatorio',
    event: 'Configuración', participants: 'Decks participantes', name: 'Nombre', code: 'Código', status: 'Estado', type: 'Tipo de evento', format: 'Formato',
    division_id: 'División principal', event_ruleset: 'Formato de juego', ruleset_version: 'Versión del reglamento', match_format: 'Participación del combate', best_of: 'Mejor de',
    starts_on: 'Fecha de inicio', ends_on: 'Fecha de cierre', rules: 'Notas y reglas', prompt_template: 'Modelo preconfigurado', event_system: 'Sistema competitivo', execution_model: 'Modelo de ejecución',
    advancement_rule: 'Regla de avance', draw_policy: 'Política de empate', game_rule_id: 'Regla aplicada', decks: 'Cantidad de decks', matches: 'Combates generados',
    active: 'Activo', completed: 'Finalizado', pending_configuration: 'Pendiente de configuración', cancelled: 'Anulado',
    promotion_slots: 'Cupos de ascenso', relegation_slots: 'Cupos de descenso', lock_division_moves: 'Bloquear movimientos',
    deck_roster_locked: 'Participantes bloqueados', existing_scheduled_matches: 'Combates ya programados', expected_matches: 'Combates previstos', reprogrammed: 'Reprogramación',
    pattern: 'Frecuencia', start_date: 'Fecha inicial', start: 'Inicio', round: 'Ronda', created: 'Creados', moves: 'Movimientos', reason: 'Motivo', source: 'Origen',
    sources: 'Fuentes de clasificados', capacity: 'Plazas', locked: 'Bloqueado', rank_from: 'Desde posición', rank_to: 'Hasta posición', moved: 'Decks trasladados'
  };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const label = key => labels[key] || String(key).replace(/_/g, ' ');
  function value(v, key = '') {
    if (v === null || v === '') return 'Sin valor';
    if (typeof v === 'boolean') return v ? 'Sí' : 'No';
    if (key === 'participants' && Array.isArray(v)) return v.map(id => (window.DML_DATA?.allDecks || []).find(d => Number(d.id) === Number(id))?.name || `Deck #${id}`).join(', ') || 'Sin participantes';
    if (typeof v === 'object') return JSON.stringify(v, null, 2);
    return label(String(v));
  }
  function detail(payload) {
    const parts = [];
    if (payload.before && payload.after) {
      for (const section of ['event', 'participants']) {
        const before = payload.before[section], after = payload.after[section];
        if (before === undefined && after === undefined) continue;
        const keys = section === 'event' ? [...new Set([...Object.keys(before || {}), ...Object.keys(after || {})])] : ['participants'];
        for (const key of keys) {
          if (['updated_at', 'created_at'].includes(key)) continue;
          const a = section === 'event' ? before?.[key] : before, b = section === 'event' ? after?.[key] : after;
          if (JSON.stringify(a) === JSON.stringify(b)) continue;
          parts.push(`<tr><th scope="row">${esc(label(key))}</th><td style="white-space:pre-wrap;overflow-wrap:anywhere">${esc(value(a, key))}</td><td style="white-space:pre-wrap;overflow-wrap:anywhere">${esc(value(b, key))}</td></tr>`);
        }
      }
      if (parts.length) return `<div class="table-responsive"><table class="table app-table"><thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead><tbody>${parts.join('')}</tbody></table></div>`;
    }
    const fields = Object.entries(payload).filter(([k]) => !['before', 'after'].includes(k));
    return fields.length ? `<dl>${fields.map(([k,v]) => `<dt>${esc(label(k))}</dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere">${esc(value(v, k))}</dd>`).join('')}</dl>` : '<p>Acción guardada. No se registraron diferencias adicionales.</p>';
  }
  async function load(append = false) {
    const sequence = ++request, id = eventId;
    message.textContent = 'Consultando historial…'; more.disabled = true;
    if (!append) { rows.replaceChildren(); cursor = null; more.hidden = true; }
    try {
      const url = new URL('./', location.href); url.search = ''; url.hash = '';
      url.searchParams.set('api', 'event_audit'); url.searchParams.set('event_id', id); url.searchParams.set('search', search.value);
      if (append && cursor) url.searchParams.set('before', cursor);
      const response = await fetch(url, {cache: 'no-store'}), data = await response.json();
      if (sequence !== request || id !== eventId) return;
      if (!response.ok || !data.ok) throw new Error(data.message || 'No se pudo consultar el historial.');
      node.querySelector('[data-event-audit-name]').textContent = data.event.name;
      for (const row of data.rows) {
        const article = document.createElement('article'); article.className = 'panel mb-3'; article.dataset.eventAuditId = row.id;
        const action = row.action === 'configure_competition' ? ({save:'Ficha y participantes guardados',start:'Evento iniciado',save_schedule:'Programación guardada',schedule_cup:'Copa programada',schedule_qualifier:'Clasificatorio programado'}[row.detail.mode] || 'Configuración guardada') : row.label;
        article.innerHTML = `<time>${esc(row.created_at)}</time><h3>${esc(action)}</h3><details><summary>Ver detalle del cambio</summary>${detail(row.detail)}</details>`;
        rows.append(article);
      }
      cursor = data.next_cursor; more.hidden = !cursor;
      message.textContent = rows.children.length ? `${rows.children.length} acciones mostradas de este evento.` : 'No hay acciones registradas que coincidan con la búsqueda.';
    } catch (error) { if (sequence === request) message.textContent = error.message; }
    finally { if (sequence === request) more.disabled = false; }
  }
  document.querySelectorAll('[data-open-event-audit]').forEach(button => button.addEventListener('click', () => {
    eventId = String(new URL(location.href).searchParams.get('event_id') || localStorage.getItem('dml_event_ficha_id') || '');
    if (!eventId) return;
    search.value = ''; node.querySelector('[data-event-audit-name]').textContent = '';
    modal.show(); load();
  }));
  node.querySelector('[data-event-audit-search-form]').addEventListener('submit', e => { e.preventDefault(); load(); });
  more.addEventListener('click', () => load(true));
  node.addEventListener('hidden.bs.modal', () => { ++request; });
})();
