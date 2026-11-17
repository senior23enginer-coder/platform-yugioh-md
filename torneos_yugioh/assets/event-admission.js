(function (root) {
  'use strict';
  function quotas(rows, capacity) {
    if (!rows.length || rows.length > 32 || !Number.isInteger(capacity) || capacity < 0 || capacity > 64) throw Error('Configura de 1 a 32 fuentes y un total de hasta 64 plazas.');
    const percent = rows.filter(r => r.mode === 'percentage').length;
    if (percent && percent !== rows.length) throw Error('No mezcles porcentajes con cupos o campeones.');
    let sum = 0;
    const result = rows.map(r => {
      if (!['winner', 'runner_up', 'top_n', 'percentage', 'minimum_points', 'best_record', 'group_position'].includes(r.mode) || (!['winner','runner_up'].includes(r.mode) && (!Number.isInteger(r.value) || r.value < 1 || r.value > (percent ? 100 : 64)))) throw Error('Los cupos y porcentajes deben ser enteros positivos.');
      sum += ['winner','runner_up'].includes(r.mode) ? 1 : r.value;
      return percent ? Math.floor(capacity * r.value / 100) : (['winner','runner_up'].includes(r.mode) ? 1 : r.value);
    });
    if (percent) {
      if (sum !== 100 || capacity < 2) throw Error('Los porcentajes deben sumar 100; configura al menos 2 plazas.');
      const order = rows.map((r, i) => ({i, remainder: capacity * r.value % 100})).sort((a, b) => b.remainder - a.remainder || a.i - b.i);
      const missing = capacity - result.reduce((a, b) => a + b, 0);
      order.slice(0, missing).forEach(r => result[r.i]++);
    } else if (capacity && result.reduce((a, b) => a + b, 0) !== capacity) throw Error('La suma de cupos debe coincidir con el total de plazas.');
    const total = result.reduce((a, b) => a + b, 0);
    if (total < 2 || total > 64) throw Error('Se necesitan entre 2 y 64 clasificados.');
    return result;
  }
  const ruleModes = {TOP_PERCENTAGE:'percentage', MINIMUM_POINTS:'minimum_points', BEST_RECORD:'best_record', RUNNER_UP:'runner_up', GROUP_POSITION:'group_position'};
  function candidates(source, row) {
    if (!['winner','runner_up','top_n','percentage','minimum_points','best_record','group_position'].includes(row.mode)) throw Error('Criterio de clasificacion desconocido.');
    if (source.grouped && row.mode !== 'group_position') throw Error('Grupos sin final requiere Posicion de grupo.');
    const threshold = row.threshold ?? 0;
    if (!Number.isInteger(threshold) || threshold < 0 || threshold > 1000000) throw Error('El umbral debe ser un entero entre 0 y 1000000.');
    let ranked = [...source.ranking];
    if (['winner','runner_up'].includes(row.mode)) {
      const id = Number(source[row.mode === 'winner' ? 'champion_id' : 'runner_up_id']);
      ranked = ranked.filter(r => Number(r.id) === id);
      if (!id || ranked.length !== 1) throw Error('La fuente no tiene campeon/subcampeon verificable.');
    }
    if (row.mode === 'minimum_points') ranked = ranked.filter(r => Number(r.pts ?? r.points ?? 0) >= threshold);
    if (row.mode === 'group_position') {
      if (!source.grouped || threshold < 1) throw Error('Selecciona una posicion positiva de una fuente con grupos.');
      ranked = ranked.filter(r => Number(r.group_position) === threshold);
    }
    if (row.mode === 'best_record') {
      if (ranked.some(r => Number(r.pj) < 1)) throw Error('Mejor record requiere encuentros disputados verificables.');
      ranked = ranked.map((r,i)=>({...r,_seed:i})).sort((a,b)=>Number(b.pg)*Number(a.pj)-Number(a.pg)*Number(b.pj) || Number(a.pp)-Number(b.pp) || a._seed-b._seed);
    }
    return ranked;
  }
  if (typeof module !== 'undefined' && module.exports) { module.exports = {quotas,candidates}; return; }
  root.StationXAdmission = {quotas,candidates};
  document.addEventListener('DOMContentLoaded', () => {
    const data = root.DML_DATA || {};
    const diagnostic = document.querySelector('[data-event-model-diagnostic]');
    if (diagnostic) {
      const note = document.createElement('p'); note.className = 'note span-all'; note.dataset.admissionCreateNote = ''; diagnostic.after(note);
      const refreshNote = () => {
        const code = document.querySelector('[data-gestion-competition-template]')?.value;
        const model = document.querySelector('[data-gestion-competition-execution-model]')?.value;
        const template = (data.eventPromptTemplates || []).find(t => t.code === code);
        const advanced = !!ruleModes[document.querySelector('[data-gestion-competition-advancement-rule]')?.value];
        note.hidden = !advanced && template?.participant_source !== 'PREVIOUS_EVENT' && !(data.executionCapabilities || {})[model]?.admission_required;
        note.textContent = 'Este evento recibe clasificados. Guarda su ficha y configura Fuentes y clasificados para elegir eventos cerrados y cargar sus cupos antes de programar.';
      };
      document.querySelectorAll('[data-gestion-competition-template], [data-gestion-competition-execution-model], [data-gestion-competition-type], [data-gestion-competition-advancement-rule]').forEach(s => s.addEventListener('change', refreshNote));refreshNote();
    }
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const modal = document.createElement('div');
    modal.className = 'modal fade dml-modal'; modal.dataset.admissionModal = ''; modal.tabIndex = -1;
    modal.innerHTML = `<div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" method="post">
      <div class="modal-header"><div><h2>Fuentes y clasificados</h2><p data-admission-title></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body"><input type="hidden" name="action" value="save_event_admission"><input type="hidden" name="competition_id"><input type="hidden" name="admission_sources_json">
        <p>Solo se ofrecen eventos cerrados con resultados completos y del mismo formato. El orden de las fuentes define la siembra y resuelve restos iguales.</p>
        <label class="d-block">Total de plazas<input class="form-control bg-dark text-light" name="admission_capacity" type="number" min="0" max="64" step="1" value="0"><small>0 suma los cupos. Para porcentajes indica el total; deben sumar 100. Se distribuyen los restos mayores, sin plazas fraccionarias.</small></label>
        <div data-admission-rows class="mt-3"></div><button type="button" class="btn btn-outline-info my-3" data-admission-add>Agregar fuente</button>
        <p class="note" data-admission-message role="status"></p><div data-admission-preview></div>
      </div><div class="modal-footer"><button type="button" class="btn btn-outline-light text-light" data-bs-dismiss="modal">Cerrar</button><button type="submit" class="btn btn-primary" data-admission-save>Guardar fuentes y cargar clasificados</button></div>
    </form></div>`;
    document.body.append(modal);
    let competition = null, rows = [], locked = false;
    const rowsNode = modal.querySelector('[data-admission-rows]');
    const capacity = modal.querySelector('[name="admission_capacity"]');
    const save = modal.querySelector('[data-admission-save]');
    const available = () => (data.admissionSources || []).filter(s => s.id !== Number(competition?.id) && s.format === competition?.event_ruleset);
    const sourceLabel = s => `${s.label} · ${s.season_name || 'Temporada #' + s.season_id} · ${(data.rulesets || []).find(r => r.code === s.deck_format)?.label || s.deck_format}`;
    function collect() {
      rows = Array.from(rowsNode.querySelectorAll('[data-admission-row]')).map(node => {
        const [source, division] = node.querySelector('[data-admission-source]').value.split(':').map(Number);
        return {source_id: source || 0, division_id: division || 0, mode: node.querySelector('[data-admission-mode]').value, value: Number(node.querySelector('[data-admission-value]').value), threshold: Number(node.querySelector('[data-admission-threshold]').value)};
      });
    }
    function preview() {
      collect(); const message = modal.querySelector('[data-admission-message]'); const previewNode = modal.querySelector('[data-admission-preview]');
      save.disabled = true; previewNode.innerHTML = '';
      if (locked) {
        message.textContent = 'El evento esta programado o cerrado. Las fuentes y sus clasificados se conservan para consulta.';
        const selection = competition?.admission?.config?.selection;
        if (selection) previewNode.innerHTML = `<p>${selection.ids.length} clasificados fijados al programar.</p>`;
        return;
      }
      try {
        const allocation = quotas(rows, Number(capacity.value)); const seen = new Set(), usedSources = new Set(), selected = [];
        rows.forEach((r, i) => {
          const key = `${r.source_id}:${r.division_id}`;
          if (usedSources.has(key)) throw Error('No repitas una fuente.'); usedSources.add(key);
          const source = available().find(s => s.id === r.source_id && s.division_id === r.division_id);
          if (!source) throw Error('Selecciona una fuente cerrada valida.');
          if (selected.some(d => d.format !== source.deck_format)) throw Error('Las fuentes no pueden mezclar formatos de decks.');
          if (['super_cup','grand_final'].includes(competition.prompt_template) && !['winner','runner_up'].includes(r.mode)) throw Error('Esta plantilla recibe campeones: selecciona Campeon.');
          const expected = ruleModes[competition.advancement_rule];
          if (expected && r.mode !== expected) throw Error('Las fuentes deben usar el criterio de avance guardado.');
          const eligible = candidates(source,r);
          if (r.mode==='group_position' && eligible.length!==allocation[i]) throw Error('Los cupos deben incluir esa posicion de todos los grupos.');
          const chosen = eligible.slice(0,allocation[i]);
          if (chosen.length !== allocation[i]) throw Error(`No hay suficientes clasificados en ${source.label}.`);
          chosen.forEach(d => { if (seen.has(Number(d.id))) throw Error('Un deck clasifica por mas de una fuente. No se asignaran sustitutos automaticamente.'); seen.add(Number(d.id)); selected.push({name:d.name, source:source.label, format:source.deck_format}); });
        });
        if (competition.execution_model === 'series' && selected.length !== 2) throw Error('Una serie necesita exactamente dos clasificados.');
        message.textContent = `${selected.length} clasificados. Cupos por fuente: ${allocation.join(', ')}. Se comprobara otra vez al guardar y programar.`;
        previewNode.innerHTML = `<ol>${selected.map(d => `<li><strong>${escape(d.name)}</strong> — ${escape(d.source)}</li>`).join('')}</ol>`;
        modal.querySelector('[name="admission_sources_json"]').value = JSON.stringify(rows); save.disabled = false;
      } catch (e) { message.textContent = e.message; }
    }
    function render() {
      const choices = available();
      rowsNode.innerHTML = rows.map((r, i) => `<section class="panel mb-3" data-admission-row>
        <label class="d-block">Fuente ${i + 1}<select class="form-select bg-dark text-light" data-admission-source ${locked?'disabled':''}><option value="">Seleccionar evento cerrado</option>${choices.map(s => `<option value="${s.id}:${s.division_id}" ${s.id===r.source_id && s.division_id===r.division_id?'selected':''}>${escape(sourceLabel(s))}</option>`).join('')}</select></label>
        <div class="two-col mt-2"><label class="d-block">Clasifican<select class="form-select bg-dark text-light" data-admission-mode ${locked||ruleModes[competition.advancement_rule]?'disabled':''}>${[['winner','Campeon'],['top_n','Primeros N'],['percentage','Porcentaje de plazas'],['runner_up','Subcampeon'],['minimum_points','Puntos minimos'],['best_record','Mejor record'],['group_position','Posicion de grupo']].map(([v,l]) => `<option value="${v}" ${v===r.mode?'selected':''}>${l}</option>`).join('')}</select></label><label class="d-block">Plazas exactas / porcentaje<input class="form-control bg-dark text-light" data-admission-value type="number" min="1" max="100" step="1" value="${['winner','runner_up'].includes(r.mode)?1:(Number(r.value)||1)}" ${locked||['winner','runner_up'].includes(r.mode)?'disabled':''}></label></div>
        <label class="d-block mt-2" ${!['minimum_points','group_position'].includes(r.mode)?'hidden':''}>${r.mode==='group_position'?'Posicion dentro de cada grupo':'Puntos minimos exigidos'}<input class="form-control bg-dark text-light" data-admission-threshold type="number" min="0" max="1000000" step="1" value="${Number(r.threshold)||0}" ${locked?'disabled':''}></label>
        ${locked?'':`<button class="btn btn-outline-light mt-2" type="button" data-admission-remove="${i}">Quitar fuente</button>`}</section>`).join('');
      rowsNode.querySelectorAll('[data-admission-remove]').forEach(b => b.addEventListener('click', () => { collect(); rows.splice(Number(b.dataset.admissionRemove),1); render(); }));
      rowsNode.querySelectorAll('[data-admission-mode]').forEach(select => select.addEventListener('change', () => { const value = select.closest('[data-admission-row]').querySelector('[data-admission-value]'); value.disabled = ['winner','runner_up'].includes(select.value); if (value.disabled) value.value = '1'; collect();render(); }));
      preview();
    }
    rowsNode.addEventListener('input', preview); rowsNode.addEventListener('change', preview); capacity.addEventListener('input', preview);
    modal.querySelector('[data-admission-add]').addEventListener('click', () => {collect();rows.push({source_id:0,division_id:0,mode:ruleModes[competition.advancement_rule] || 'top_n',value:2,threshold:0});render();});
    modal.querySelector('form').addEventListener('submit', e => {preview();if(save.disabled)e.preventDefault();});
    document.querySelectorAll('[data-open-event-admission]').forEach(button => button.addEventListener('click', () => {
      const id = new URLSearchParams(location.search).get('event_id') || document.querySelector('[data-competition-config-id]')?.value;
      competition = (data.competitions || []).find(c => String(c.id) === String(id)); if (!competition) return;
      const config = competition.admission?.config;
      locked = !!config?.frozen || Number(competition.configuration_locked) === 1 || ['completed','closed','archived','cancelled'].includes(competition.status);
      rows = (config?.sources || [{source_id:0,division_id:0,mode:ruleModes[competition.advancement_rule] || (['super_cup','grand_final'].includes(competition.prompt_template)?'winner':(competition.prompt_template==='champions_cup'?'percentage':'top_n')),value:competition.prompt_template==='champions_cup'?50:2}]).map(r => ({...r}));
      modal.querySelector('[name="competition_id"]').value = competition.id; capacity.value = config?.capacity ?? (competition.prompt_template==='champions_cup'?8:0); capacity.disabled = locked;
      modal.querySelector('[data-admission-title]').textContent = competition.name;
      modal.querySelector('[data-admission-add]').hidden = locked; save.hidden = locked;
      render(); bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
  });
})(typeof window !== 'undefined' ? window : globalThis);
