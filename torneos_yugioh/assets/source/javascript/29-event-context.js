/**
 * event-context: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function eventTypeLabel(type) {
    return {
      qualifier: 'Clasificatorio',
      tournament: 'Torneo',
      league: 'Liga',
      cup: 'Copa',
      world: 'Mundial',
      final: 'Final',
      friendly: 'Amistoso',
      special: 'Evento especial'
    }[type] || 'Evento';
  }
  function rulesetLabel(ruleset) {
    return {
      tcg_advanced: 'TCG Avanzado',
      ocg: 'OCG',
      goat: 'Goat',
      edison: 'Edison',
      speed_duel: 'Speed Duel',
      genesis: 'Genesis',
      custom: 'Personalizado'
    }[ruleset] || ruleset || 'Personalizado';
  }
  function poolLabel(pool) {
    return {
      all_active: 'Todos los decks activos',
      division: 'Formato asociado',
      qualified: 'Clasificados por evento previo',
      top_cut: 'Top cut por ranking',
      manual: 'Seleccion manual'
    }[pool] || pool || 'Manual';
  }
  function setEventDetailText(selector, value) {
    const node = eventDetail ? eventDetail.querySelector(selector) : null;
    if (node) node.textContent = value == null || value === '' ? '-' : String(value);
  }
  function schedulePatternLabel(pattern) {
    return {
      consecutive: 'Dias seguidos',
      every_other_day: '1 dia de por medio',
      every_2_days: '2 dias de por medio',
      every_3_days: '3 dias de por medio',
      weekend: 'Fin de semana'
    }[pattern] || pattern || 'Manual';
  }
  function gameRuleLabel(ruleId) {
    const rule = (data.gameRules || []).find((item) => String(item.id || '') === String(ruleId || ''));
    return rule ? (rule.name || rule.code || 'Regla') : 'Sin regla especial';
  }
  function gameRuleForId(ruleId) {
    return (data.gameRules || []).find((item) => String(item.id || '') === String(ruleId || '')) || null;
  }
  function currentCompetitionConfig() {
    return (data.competitions || []).find((item) => String(item.id || '') === String(activeCompetitionConfigId || '')) || null;
  }
  function activeCompetitionRule(competition) {
    const selectedRuleId = competitionConfigModal?.querySelector('[data-competition-config-rule]')?.value || competition?.game_rule_id || '';
    return gameRuleForId(selectedRuleId);
  }
  function ruleConditionLabel(condition) {
    const label = condition.condition_label || condition.label || '';
    const type = condition.condition_type || '';
    const operator = condition.operator || '';
    const target = condition.target_value || condition.result_value || condition.status_value || condition.score_value || '';
    return label || [type, operator, target].filter(Boolean).join(' ') || 'Condicion';
  }
  function renderCompetitionRuleSummary(competition) {
    const rule = activeCompetitionRule(competition);
    const card = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-rule-card]') : null;
    const nameNode = card ? card.querySelector('[data-competition-rule-name]') : null;
    const codeNode = card ? card.querySelector('[data-competition-rule-code]') : null;
    const effectNode = card ? card.querySelector('[data-competition-rule-effect]') : null;
    if (nameNode) nameNode.textContent = rule ? (rule.name || rule.code || 'Regla') : 'Regla compatible por defecto';
    if (codeNode) {
      codeNode.textContent = rule
        ? `${rule.code || 'SIN_CODIGO'} | ${rule.rule_type || 'general'} | ${rule.scope || 'general'}`
        : 'Sin regla especial seleccionada en el evento.';
    }
    if (effectNode) {
      effectNode.textContent = rule
        ? (rule.effect || rule.notes || 'Esta regla se aplicara al validar y guardar los resultados de los duelos.')
        : 'El sistema usara la validacion compatible segun el tipo de evento y el reglamento elegido.';
    }
    const title = eventRuleModalNode ? eventRuleModalNode.querySelector('[data-event-rule-modal-title]') : null;
    const meta = eventRuleModalNode ? eventRuleModalNode.querySelector('[data-event-rule-modal-meta]') : null;
    const body = eventRuleModalNode ? eventRuleModalNode.querySelector('[data-event-rule-modal-body]') : null;
    if (title) title.textContent = rule ? (rule.name || rule.code || 'Regla del evento') : 'Regla compatible por defecto';
    if (meta) {
      meta.textContent = rule
        ? `${eventTypeLabel(competition?.type)} | ${competition?.code || 'Evento'} | ${rule.code || 'SIN_CODIGO'}`
        : `${eventTypeLabel(competition?.type)} | ${competition?.code || 'Evento'} | Sin regla especial`;
    }
    if (!body) return;
    const policyText = (data.executionCapabilities || {})[competition?.execution_model]?.policy ? workflowPolicyDescription(competition.execution_model, competition.workflow || null) : '';
    const policyHtml = policyText ? `<article class="event-rule-effect"><h3>Organizacion del evento</h3><p>${escapeHtml(policyText)}</p></article>` : '';
    if (!rule) {
      body.innerHTML = '<p class="note">Este evento no tiene una regla custom asignada. Se aplicara la validacion compatible por defecto del tipo de evento.</p>';
      body.insertAdjacentHTML('afterbegin', policyHtml);
      return;
    }
    const conditions = Array.isArray(rule.conditions) ? rule.conditions.filter((condition) => String(condition.active ?? '1') !== '0') : [];
    body.innerHTML = `
      ${policyHtml}
      <div class="event-rule-detail-head">
        <article><span>Codigo</span><b>${escapeHtml(rule.code || '-')}</b></article>
        <article><span>Tipo</span><b>${escapeHtml(rule.rule_type || 'general')}</b></article>
        <article><span>Alcance</span><b>${escapeHtml(rule.scope || 'general')}</b></article>
        <article><span>Condiciones</span><b>${conditions.length}</b></article>
      </div>
      <article class="event-rule-effect">
        <span>Efecto en los duelos</span>
        <p>${escapeHtml(rule.effect || rule.notes || 'Sin descripcion registrada.')}</p>
      </article>
      <div class="event-rule-condition-list">
        ${conditions.length ? conditions.map((condition, index) => `
          <article>
            <b>${index + 1}</b>
            <span>${escapeHtml(ruleConditionLabel(condition))}</span>
            <small>${escapeHtml(condition.failure_message || condition.description || 'Validacion activa')}</small>
          </article>
        `).join('') : '<p class="note">Esta regla no tiene condiciones activas registradas.</p>'}
      </div>
    `;
  }
  function normalizeCompetitionStatus(status) {
    const normalized = normalizeStatusKey(status);
    if (['draft', ''].includes(normalized)) return 'pending_configuration';
    if (['cancelled', 'canceled', 'anulado', 'anulada'].includes(normalized)) return 'cancelled';
    if (['finalizada', 'finalizado', 'finalized'].includes(normalized)) return 'completed';
    if (['pendiente_evento'].includes(normalized)) return 'pending_event';
    if (['pendiente_configuracion'].includes(normalized)) return 'pending_configuration';
    return String(status || 'pending_configuration');
  }
  function competitionStatusLabel(status) {
    return {
      pending_configuration: 'Pendiente configuracion',
      pending_event: 'Programado',
      active: 'Activo',
      completed: 'Finalizado',
      cancelled: 'Anulado'
    }[normalizeCompetitionStatus(status)] || status || 'Pendiente configuracion';
  }
  function competitionIsCancelled(status) {
    return normalizeCompetitionStatus(status) === 'cancelled';
  }
  function competitionIsTerminal(status) {
    const normalized = normalizeCompetitionStatus(status);
    return ['completed', 'cancelled'].includes(normalized);
  }

  const competitionConfigModal = document.querySelector('[data-competition-config-modal]');
  const eventLogoModalFields = competitionConfigModal?.querySelector('[data-event-logo-modal-fields]');
  const eventLogoInput = competitionConfigModal?.querySelector('[data-competition-config-logo-input]');
  const eventRemoveLogo = competitionConfigModal?.querySelector('[data-competition-config-remove-logo]');
  const eventLogoGallery = competitionConfigModal?.querySelector('[data-event-logo-gallery]');
  [eventLogoInput?.closest('label'), eventRemoveLogo?.closest('label'), eventLogoGallery].filter(Boolean).forEach((node) => eventLogoModalFields?.appendChild(node));
  const eventLogoModalNode = document.getElementById('eventLogoModal');
  const eventLogoSave = eventLogoModalNode?.querySelector('[data-event-logo-save]');
  const eventLogoMessage = eventLogoModalNode?.querySelector('[data-event-logo-message]');
  let eventLogoSession = null, eventLogoSaving = false, eventLogoRequest = 0;
  function eventLogoUrl(id) {
    const url = new URL('./', location.href);
    url.searchParams.set('api', 'event_logo'); url.searchParams.set('event_id', id);
    return url;
  }
  function resetEventLogoSelection(competition) {
    if (!competition) return;
    if (eventLogoInput) eventLogoInput.value = '';
    if (eventRemoveLogo) eventRemoveLogo.checked = false;
    const galleryInput = eventLogoGallery?.querySelector('[data-event-logo-gallery-input]');
    if (galleryInput) galleryInput.value = '';
    renderEventLogoGallery(eventLogoGallery, competition.type || 'special', competition.logo_url || '');
    const image = competitionConfigModal?.querySelector('[data-competition-config-logo]');
    const wrap = competitionConfigModal?.querySelector('[data-competition-config-logo-wrap]');
    if (image) image.src = competition.logo_url || '';
    if (wrap) wrap.hidden = !competition.logo_url;
  }
  eventLogoModalNode?.addEventListener('show.bs.modal', async () => {
    const competition = currentCompetitionConfig(), request = ++eventLogoRequest;
    eventLogoSession = null; eventLogoSave.disabled = true;
    eventLogoMessage.textContent = 'Consultando logo guardado…';
    resetEventLogoSelection(competition);
    if (!competition) return;
    try {
      const response = await fetch(eventLogoUrl(competition.id), {cache: 'no-store'}), result = await response.json();
      if (request !== eventLogoRequest) return;
      if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo consultar el logo.');
      competition.logo_url = result.logo_url;
      eventLogoSession = {...result, eventId: competition.id};
      eventLogoSave.disabled = false; eventLogoMessage.textContent = '';
    } catch (error) { if (request === eventLogoRequest) eventLogoMessage.textContent = error.message; }
  });
  eventLogoModalNode?.addEventListener('hide.bs.modal', event => { if (eventLogoSaving) event.preventDefault(); });
  eventLogoModalNode?.addEventListener('hidden.bs.modal', () => {
    ++eventLogoRequest; eventLogoSession = null;
    resetEventLogoSelection(currentCompetitionConfig());
  });
  eventLogoGallery?.addEventListener('click', event => {
    if (!event.target.closest('[data-event-logo-choice]')) return;
    if (eventLogoInput) eventLogoInput.value = '';
    if (eventRemoveLogo) eventRemoveLogo.checked = false;
  });
  eventLogoSave?.addEventListener('click', async () => {
    if (!eventLogoSession || eventLogoSaving) return;
    const competition = currentCompetitionConfig();
    if (String(competition?.id) !== String(eventLogoSession.eventId)) return;
    eventLogoSaving = true; eventLogoSave.disabled = true; eventLogoMessage.textContent = 'Guardando logo…';
    try {
      const body = new FormData(); body.set('token', eventLogoSession.token); body.set('version', eventLogoSession.version);
      body.set('competition_gallery_logo', eventLogoGallery?.querySelector('[data-event-logo-gallery-input]')?.value || '');
      if (eventRemoveLogo?.checked) body.set('competition_remove_logo', '1');
      if (eventLogoInput?.files?.[0]) body.set('competition_logo', eventLogoInput.files[0]);
      const response = await fetch(eventLogoUrl(competition.id), {method:'POST', body, cache:'no-store'}), result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo guardar el logo.');
      competition.logo_url = result.logo_url;
      resetEventLogoSelection(competition);
      eventLogoSaving = false;
      bootstrap.Modal.getInstance(eventLogoModalNode)?.hide();
    } catch (error) { eventLogoMessage.textContent = error.message; }
    finally { eventLogoSaving = false; eventLogoSave.disabled = !eventLogoSession; }
  });
  const eventProgrammingPanel = competitionConfigModal?.querySelector('[data-event-programming-panel]');
  const eventProgrammingBody = competitionConfigModal?.querySelector('[data-event-programming-modal-body]');
  if (eventProgrammingPanel && eventProgrammingBody) eventProgrammingBody.appendChild(eventProgrammingPanel);
  const eventDataActions = competitionConfigModal?.querySelector('.event-data-actions');
  [competitionConfigModal?.querySelector('[data-open-event-ranking-modal]'), competitionConfigModal?.querySelector('[data-open-event-schedule-modal]')]
    .filter(Boolean).forEach((button) => eventDataActions?.appendChild(button));
  const eventRankingModalNode = document.querySelector('[data-event-ranking-modal]');
  const eventRankingModal = eventRankingModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventRankingModalNode) : null;
  const eventRuleModalNode = document.querySelector('[data-event-rule-modal]');
  const eventRuleModal = eventRuleModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventRuleModalNode) : null;
  const eventScheduleModalNode = document.querySelector('[data-event-schedule-modal]');
  const eventScheduleModal = eventScheduleModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventScheduleModalNode) : null;
  const eventProgrammingModalNode = document.getElementById('eventProgrammingModal');
  const eventProgrammingModal = eventProgrammingModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(eventProgrammingModalNode) : null;
  const confirmStartQualifierModalNode = document.querySelector('[data-confirm-start-qualifier-modal]');
  const confirmStartQualifierModal = confirmStartQualifierModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(confirmStartQualifierModalNode) : null;
  const confirmScheduleQualifierModalNode = document.querySelector('[data-confirm-schedule-qualifier-modal]');
  const confirmScheduleQualifierModal = confirmScheduleQualifierModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(confirmScheduleQualifierModalNode) : null;
  const competitionDecksModalNode = document.querySelector('[data-competition-decks-modal]');
  const competitionDecksModal = competitionDecksModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(competitionDecksModalNode) : null;
  const deleteCompetitionModalNode = document.querySelector('[data-delete-competition-modal]');
  const deleteCompetitionModal = deleteCompetitionModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(deleteCompetitionModalNode) : null;
  const qualifierLoadModalNode = document.querySelector('[data-qualifier-load-modal]');
  const qualifierLoadModal = qualifierLoadModalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(qualifierLoadModalNode) : null;
  const competitionConfigCount = document.querySelector('[data-competition-config-count]');
  let competitionSelectedDeckIds = new Set();
  let participantDivisionView = "";
  let participantDivisionEvent = "";
  let activeCompetitionConfigId = '';
  let activeQualifierLoadId = '';
