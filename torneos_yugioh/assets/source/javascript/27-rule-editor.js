/**
 * rule-editor: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function setScopedValue(root, selector, value) {
    const node = root ? root.querySelector(selector) : null;
    if (node) node.value = value == null ? '' : value;
  }

  function setScopedChecked(root, selector, checked) {
    const node = root ? root.querySelector(selector) : null;
    if (node) node.checked = Boolean(checked);
  }

  function renumberRuleConditions() {
    if (!ruleConditionList) return;
    Array.from(ruleConditionList.querySelectorAll('.rule-condition-row')).forEach((row, index) => {
      [
        ['[data-condition-id]', conditionFieldNames.id],
        ['[data-condition-type]', conditionFieldNames.type],
        ['[data-condition-label]', conditionFieldNames.label],
        ['[data-condition-operator]', conditionFieldNames.operator],
        ['[data-condition-target]', conditionFieldNames.target],
        ['[data-condition-result]', 'condition_result'],
        ['[data-condition-status]', 'condition_status'],
        ['[data-condition-score]', 'condition_score'],
        ['[data-condition-message]', conditionFieldNames.message],
        ['[data-condition-active]', conditionFieldNames.active]
      ].forEach(([selector, name]) => {
        const node = row.querySelector(selector);
        if (node) node.name = `${name}[${index}]`;
      });
    });
  }

  function conditionTypeLabel(value) {
    return {
      result_case: 'Caso D1-D5',
      victory: 'Victoria',
      defeat: 'Derrota',
      draw: 'Empate',
      validation: 'Validacion',
      score: 'Puntaje / XP',
      availability: 'Disponibilidad',
      custom: 'Custom'
    }[value] || value || 'Validacion';
  }

  function conditionOperatorLabel(value) {
    return {
      sequence_equals: 'Secuencia exacta',
      defined: 'Debe estar definido',
      winner_in: 'Ganador en lista',
      winner_equals: 'Ganador igual a',
      score_min: 'Marcador minimo',
      score_max: 'Marcador maximo',
      points_value: 'Valor de puntos',
      requires_previous: 'Requiere resultado previo',
      blocks_when: 'Bloquea cuando',
      custom: 'Custom'
    }[value] || value || 'Definido';
  }

  function conditionResultLabel(value) {
    return { A: 'Gana A', B: 'Gana B', E: 'Empate' }[value] || 'Sin ganador';
  }

  function getConditionRowData(row) {
    return {
      id: row.querySelector('[data-condition-id]')?.value || '',
      condition_type: row.querySelector('[data-condition-type]')?.value || 'validation',
      condition_label: row.querySelector('[data-condition-label]')?.value || '',
      operator: row.querySelector('[data-condition-operator]')?.value || 'sequence_equals',
      target_value: row.querySelector('[data-condition-target]')?.value || '',
      result_value: row.querySelector('[data-condition-result]')?.value || '',
      status_value: row.querySelector('[data-condition-status]')?.value || 'FINALIZADA',
      score_value: row.querySelector('[data-condition-score]')?.value || '',
      failure_message: row.querySelector('[data-condition-message]')?.value || '',
      active: row.querySelector('[data-condition-active]')?.value === '1' ? '1' : '0'
    };
  }

  function renderConditionRow(row) {
    const condition = getConditionRowData(row);
    const labelText = row.querySelector('[data-condition-label-text]');
    const typeText = row.querySelector('[data-condition-type-text]');
    const operatorText = row.querySelector('[data-condition-operator-text]');
    const targetText = row.querySelector('[data-condition-target-text]');
    const resultText = row.querySelector('[data-condition-result-text]');
    const statusText = row.querySelector('[data-condition-status-text]');
    const messageText = row.querySelector('[data-condition-message-text]');
    if (labelText) labelText.textContent = condition.condition_label || 'Sin nombre';
    if (typeText) typeText.textContent = conditionTypeLabel(condition.condition_type);
    if (operatorText) operatorText.textContent = conditionOperatorLabel(condition.operator);
    if (targetText) targetText.textContent = condition.target_value || '-';
    if (resultText) resultText.textContent = conditionResultLabel(condition.result_value);
    if (statusText) {
      statusText.textContent = condition.active === '1' ? (condition.status_value || 'FINALIZADA') : 'Inactiva';
      statusText.classList.toggle('state-cancelled', condition.active !== '1');
      statusText.classList.toggle('state-active', condition.active === '1' && condition.status_value === 'FINALIZADA');
      statusText.classList.toggle('danger', condition.active === '1' && condition.status_value === 'INVALID');
      statusText.classList.toggle('warn', condition.active === '1' && condition.status_value === 'REQUIERE D5');
    }
    if (messageText) messageText.textContent = condition.failure_message || condition.condition_label || '-';
    row.classList.toggle('is-inactive', condition.active !== '1');
    row.classList.toggle('is-invalid-condition', condition.active === '1' && condition.status_value === 'INVALID');
  }

  function setConditionRowData(row, condition = {}) {
    setScopedValue(row, '[data-condition-id]', condition.id || '');
    setScopedValue(row, '[data-condition-type]', condition.condition_type || 'validation');
    setScopedValue(row, '[data-condition-label]', condition.condition_label || '');
    setScopedValue(row, '[data-condition-operator]', condition.operator || 'sequence_equals');
    setScopedValue(row, '[data-condition-target]', condition.target_value || '');
    setScopedValue(row, '[data-condition-result]', condition.result_value || '');
    setScopedValue(row, '[data-condition-status]', condition.status_value || 'FINALIZADA');
    setScopedValue(row, '[data-condition-score]', condition.score_value || '');
    setScopedValue(row, '[data-condition-message]', condition.failure_message || '');
    setScopedValue(row, '[data-condition-active]', condition.active === undefined || condition.active === 1 || condition.active === '1' ? '1' : '0');
    renderConditionRow(row);
  }

  function addRuleCondition(condition = {}) {
    if (!ruleConditionList || !ruleConditionTemplate) return;
    const fragment = ruleConditionTemplate.content.cloneNode(true);
    const row = fragment.querySelector('.rule-condition-row');
    if (!row) return;
    row.dataset.conditionUid = `condition_${Date.now()}_${Math.random().toString(16).slice(2)}`;
    setConditionRowData(row, condition);
    const edit = row.querySelector('[data-condition-edit]');
    if (edit) edit.addEventListener('click', () => openConditionModal(row));
    const remove = row.querySelector('[data-condition-remove]');
    if (remove) {
      remove.addEventListener('click', () => {
        row.remove();
        renumberRuleConditions();
      });
    }
    ruleConditionList.appendChild(row);
    renumberRuleConditions();
    return row;
  }

  function fillConditionModal(condition = {}, row = null) {
    if (!ruleConditionModalNode) return;
    if (conditionModalFields.row) conditionModalFields.row.value = row ? (row.dataset.conditionUid || '') : '';
    if (conditionModalFields.title) conditionModalFields.title.textContent = row ? 'Editar condicion' : 'Nueva condicion';
    if (conditionModalFields.type) conditionModalFields.type.value = condition.condition_type || 'result_case';
    if (conditionModalFields.label) conditionModalFields.label.value = condition.condition_label || '';
    if (conditionModalFields.operator) conditionModalFields.operator.value = condition.operator || 'sequence_equals';
    if (conditionModalFields.target) conditionModalFields.target.value = condition.target_value || '';
    if (conditionModalFields.result) conditionModalFields.result.value = condition.result_value || '';
    if (conditionModalFields.status) conditionModalFields.status.value = condition.status_value || 'FINALIZADA';
    if (conditionModalFields.score) conditionModalFields.score.value = condition.score_value || '';
    if (conditionModalFields.message) conditionModalFields.message.value = condition.failure_message || '';
    if (conditionModalFields.active) conditionModalFields.active.checked = condition.active === undefined || condition.active === 1 || condition.active === '1';
  }

  function openConditionModal(row = null) {
    fillConditionModal(row ? getConditionRowData(row) : {}, row);
    if (ruleConditionModal) {
      ruleConditionModal.show();
    }
  }

  function saveConditionFromModal() {
    if (!ruleConditionList) return;
    const label = (conditionModalFields.label?.value || '').trim();
    if (!label) {
      alert('La condicion necesita un nombre.');
      conditionModalFields.label?.focus();
      return;
    }
    const uid = conditionModalFields.row?.value || '';
    const row = uid
      ? Array.from(ruleConditionList.querySelectorAll('.rule-condition-row')).find((item) => item.dataset.conditionUid === uid)
      : addRuleCondition();
    if (!row) return;
    setConditionRowData(row, {
      id: row.querySelector('[data-condition-id]')?.value || '',
      condition_type: conditionModalFields.type?.value || 'validation',
      condition_label: label,
      operator: conditionModalFields.operator?.value || 'sequence_equals',
      target_value: conditionModalFields.target?.value || '',
      result_value: conditionModalFields.result?.value || '',
      status_value: conditionModalFields.status?.value || 'FINALIZADA',
      score_value: conditionModalFields.score?.value || '',
      failure_message: conditionModalFields.message?.value || '',
      active: conditionModalFields.active?.checked ? '1' : '0'
    });
    renumberRuleConditions();
    if (ruleConditionModal) ruleConditionModal.hide();
  }

  function fillRuleDetail(ruleId = '') {
    if (!ruleDetailScreen) return;
    const rule = gameRulesById.get(String(ruleId || '')) || {};
    const isNew = !rule.id;
    setScopedValue(ruleDetailScreen, '[data-game-rule-id]', rule.id || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-code]', rule.code || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-name]', rule.name || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-type]', rule.rule_type || 'xp');
    setScopedValue(ruleDetailScreen, '[data-game-rule-scope]', rule.scope || 'event');
    setScopedValue(ruleDetailScreen, '[data-game-rule-priority]', rule.priority_order || '100');
    setScopedValue(ruleDetailScreen, '[data-game-rule-trigger]', rule.trigger_event || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-effect]', rule.effect || '');
    setScopedValue(ruleDetailScreen, '[data-game-rule-points]', rule.points_value || '0');
    setScopedValue(ruleDetailScreen, '[data-game-rule-notes]', rule.notes || '');
    setScopedChecked(ruleDetailScreen, '[data-game-rule-active]', isNew || rule.active === 1 || rule.active === '1');
    const archiveId = ruleDetailScreen.querySelector('[data-game-rule-archive-id]');
    if (archiveId) archiveId.value = rule.id || '';
    if (ruleArchiveForm) ruleArchiveForm.hidden = isNew;
    const heading = ruleDetailScreen.querySelector('[data-rule-detail-heading]');
    if (heading) heading.textContent = isNew ? 'Nueva regla' : `Regla: ${rule.name || rule.code}`;
    if (ruleConditionList) {
      ruleConditionList.innerHTML = '';
      const conditions = Array.isArray(rule.conditions) ? rule.conditions : [];
      if (conditions.length) {
        conditions.forEach((condition) => addRuleCondition(condition));
      }
    }
  }

  function applyRuleFilters() {
    const rows = Array.from(document.querySelectorAll('[data-rule-row]'));
    const query = (ruleSearchInput?.value || '').trim().toLowerCase();
    const type = ruleTypeFilter?.value || '';
    const status = ruleStatusFilter?.value || '';
    let visible = 0;
    rows.forEach((row) => {
      const show = (!query || (row.dataset.ruleSearch || '').includes(query))
        && (!type || row.dataset.ruleType === type)
        && (!status || row.dataset.ruleStatus === status);
      row.hidden = !show;
      if (show) visible += 1;
    });
    if (ruleVisibleCount) ruleVisibleCount.textContent = `${visible} reglas`;
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-rule-open]');
    if (!button) return;
    event.preventDefault();
    fillRuleDetail(button.dataset.ruleOpen || '');
    routeTo('regla_ficha');
  });

  const ruleConditionAdd = ruleDetailScreen ? ruleDetailScreen.querySelector('[data-rule-condition-add]') : null;
  if (ruleConditionAdd) ruleConditionAdd.addEventListener('click', () => openConditionModal());
  if (conditionModalFields.save) conditionModalFields.save.addEventListener('click', saveConditionFromModal);
  [ruleSearchInput, ruleTypeFilter, ruleStatusFilter].forEach((node) => {
    if (node) node.addEventListener('input', applyRuleFilters);
    if (node) node.addEventListener('change', applyRuleFilters);
  });
  const ruleFilterClear = document.querySelector('[data-rule-filter-clear]');
  if (ruleFilterClear) {
    ruleFilterClear.addEventListener('click', () => {
      if (ruleSearchInput) ruleSearchInput.value = '';
      if (ruleTypeFilter) ruleTypeFilter.value = '';
      if (ruleStatusFilter) ruleStatusFilter.value = '';
      applyRuleFilters();
    });
  }
  if (ruleDetailForm) {
    ruleDetailForm.addEventListener('submit', (event) => {
      const labels = Array.from(ruleDetailForm.querySelectorAll('[data-condition-label]')).map((input) => input.value.trim()).filter(Boolean);
      if (!labels.length) {
        event.preventDefault();
        alert('Agrega al menos una condicion para guardar la regla.');
      }
      renumberRuleConditions();
    });
  }
  applyRuleFilters();
  if (gameRuleSelect) {
    gameRuleSelect.addEventListener('change', () => {
      const rule = gameRulesById.get(String(gameRuleSelect.value || '')) || {};
      setValue('[data-game-rule-id]', rule.id || '');
      setValue('[data-game-rule-code]', rule.code || '');
      setValue('[data-game-rule-name]', rule.name || '');
      setValue('[data-game-rule-type]', rule.rule_type || 'xp');
      setValue('[data-game-rule-scope]', rule.scope || 'event');
      setValue('[data-game-rule-priority]', rule.priority_order || '100');
      setValue('[data-game-rule-trigger]', rule.trigger_event || '');
      setValue('[data-game-rule-effect]', rule.effect || '');
      setValue('[data-game-rule-points]', rule.points_value || '0');
      setValue('[data-game-rule-notes]', rule.notes || '');
      const active = document.querySelector('[data-game-rule-active]');
      if (active) active.checked = !rule.id || rule.active === 1 || rule.active === '1';
      if (gameRuleArchiveId) gameRuleArchiveId.value = rule.id || '';
    });
  }
  applyRulesetToCompetition(false);
  selectCompetitionRulesetVersion('');
  syncParticipantsFromMatchFormat();
  updateCompetitionCodePreview();

  const eventFilterState = { query: '', type: '', ruleset: '', status: '', division: '', season: new URLSearchParams(location.search).get('season_context') || '' };
