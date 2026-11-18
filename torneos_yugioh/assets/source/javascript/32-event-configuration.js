/**
 * event-configuration: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function updateCompetitionConfigVisibility() {
    if (!competitionConfigModal) return;
    const typeSelect = competitionConfigModal.querySelector('[data-competition-config-type]');
    const type = typeSelect ? typeSelect.value : '';
    const conditionWrap = competitionConfigModal.querySelector('[data-competition-config-condition-wrap]');
    const conditionSelect = competitionConfigModal.querySelector('[data-competition-config-condition]');
    const qualifierAction = competitionConfigModal.querySelector('[data-competition-config-qualifier-action]');
    const cupAction = competitionConfigModal.querySelector('[data-competition-config-cup-action]');
    const qualifierDuelsWrap = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels-wrap]');
    const matchesPerDayWrap = competitionConfigModal.querySelector('[data-competition-config-matches-per-day-wrap]');
    const leagueMovement = competitionConfigModal.querySelector('[data-competition-league-movement]');
    const divisionWrap = competitionConfigModal.querySelector('[data-competition-config-division-wrap]');
    const divisionsWrap = competitionConfigModal.querySelector('[data-competition-config-divisions-wrap]');
    const qualifierLoadButton = competitionConfigModal.querySelector('[data-qualifier-load-open]');
    const needsCondition = ['league', 'tournament', 'world'].includes(type);
    if (conditionWrap) conditionWrap.hidden = !needsCondition;
    if (conditionSelect && !needsCondition) conditionSelect.value = '';
    if (qualifierAction) qualifierAction.hidden = type !== 'qualifier';
    if (cupAction) cupAction.hidden = !['cup', 'final'].includes(type);
    if (qualifierDuelsWrap) qualifierDuelsWrap.hidden = type !== 'qualifier';
    if (matchesPerDayWrap) matchesPerDayWrap.hidden = !['qualifier', 'cup', 'final'].includes(type);
    const competition = currentCompetitionConfig() || {};
    const selectedDivisionCount = leagueMovementDivisions(competition).length;
    const movementOpen = competitionConfigModal.querySelector('[data-league-movement-open]');
    if (movementOpen) movementOpen.hidden = type !== 'league' || selectedDivisionCount < 2;
    if (leagueMovement) leagueMovement.hidden = type !== 'league' || selectedDivisionCount < 2;
    if (divisionWrap) divisionWrap.hidden = type !== 'league';
    if (divisionsWrap) divisionsWrap.hidden = type !== 'league';
    if (qualifierLoadButton) qualifierLoadButton.hidden = !['cup', 'tournament'].includes(type);
    updateCompetitionDeckConstraints();
  }
  document.querySelectorAll('[data-competition-config]').forEach((button) => {
    button.addEventListener('click', () => fillCompetitionConfig(button.dataset.competitionConfig));
  });
  const competitionConfigType = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-type]') : null;
  if (competitionConfigType) competitionConfigType.addEventListener('change', updateCompetitionConfigVisibility);
  const competitionConfigRulesetVersion = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-ruleset-version]') : null;
  const competitionConfigRuleset = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-ruleset]') : null;
  const competitionConfigDivision = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-division]') : null;
  if (competitionConfigDivision) competitionConfigDivision.addEventListener('change', () => {
    syncRulesetFromDivision(competitionConfigDivision, competitionConfigRuleset);
    syncMovementTargetOptions(competitionConfigDivision.value, '[data-competition-config-promotion-to]', '[data-competition-config-relegation-to]');
    syncCompetitionDeckPoolNote();
    renderCompetitionSelectedDecks();
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
  });
  const competitionConfigDivisions = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-divisions]') : null;
  if (competitionConfigDivisions) competitionConfigDivisions.addEventListener('change', () => {
    const selectedIds = Array.from(competitionConfigDivisions.selectedOptions).map((option) => option.value);
    if (competitionConfigDivision && competitionConfigDivision.value && !selectedIds.includes(competitionConfigDivision.value)) {
      const primaryOption = Array.from(competitionConfigDivisions.options).find((option) => option.value === competitionConfigDivision.value);
      if (primaryOption) primaryOption.selected = true;
    }
    if (competitionConfigDivision && !competitionConfigDivision.value && selectedIds.length) {
      competitionConfigDivision.value = selectedIds[0];
    }
    updateCompetitionConfigVisibility();
    updateCompetitionDeckConstraints();
  });
  if (competitionConfigRuleset) competitionConfigRuleset.addEventListener('change', () => { refreshFormatParticipants(); updateCompetitionConfigVisibility(); });
  const competitionConfigDeckPool = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-deck-pool]') : null;
  if (competitionConfigDeckPool) competitionConfigDeckPool.addEventListener('change', refreshFormatParticipants);
  const competitionConfigMatch = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-match]') : null;
  const competitionConfigQualifierDuels = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]') : null;
  const competitionConfigStart = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-start]') : null;
  const competitionConfigStatus = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-status]') : null;
  if (competitionConfigMatch) competitionConfigMatch.addEventListener('change', () => {
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
  });
  if (competitionConfigQualifierDuels) competitionConfigQualifierDuels.addEventListener('change', updateCompetitionDeckConstraints);
  if (competitionConfigStart) competitionConfigStart.addEventListener('change', updateCompetitionDeckConstraints);
  if (competitionConfigStatus) competitionConfigStatus.addEventListener('change', () => {
    if (competitionConfigStatus.value === 'cancelled') {
      const statusNote = competitionConfigModal?.querySelector('[data-competition-status-note]');
      if (statusNote) statusNote.textContent = 'El evento quedara anulado al guardar la ficha.';
    } else {
      updateCompetitionCancelledLock(competitionConfigStatus.value);
    }
    updateCompetitionDeckConstraints();
  });
  const competitionConfigLogoInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-logo-input]') : null;
  if (competitionConfigLogoInput) {
    competitionConfigLogoInput.addEventListener('change', () => {
      const file = competitionConfigLogoInput.files && competitionConfigLogoInput.files[0] ? competitionConfigLogoInput.files[0] : null;
      const logoWrap = competitionConfigModal.querySelector('[data-competition-config-logo-wrap]');
      const logoImg = competitionConfigModal.querySelector('[data-competition-config-logo]');
      const removeLogoInput = competitionConfigModal.querySelector('[data-competition-config-remove-logo]');
      if (!logoWrap || !logoImg || !file) return;
      if (removeLogoInput) removeLogoInput.checked = false;
      logoImg.src = URL.createObjectURL(file);
      logoWrap.hidden = false;
    });
  }
  const competitionRemoveLogoInput = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-config-remove-logo]') : null;
  if (competitionRemoveLogoInput) {
    competitionRemoveLogoInput.addEventListener('change', () => {
      if (!competitionRemoveLogoInput.checked) return;
      const logoWrap = competitionConfigModal.querySelector('[data-competition-config-logo-wrap]');
      const logoImg = competitionConfigModal.querySelector('[data-competition-config-logo]');
      const logoInput = competitionConfigModal.querySelector('[data-competition-config-logo-input]');
      if (logoInput) logoInput.value = '';
      if (logoImg) logoImg.src = '';
      if (logoWrap) logoWrap.hidden = true;
    });
  }
  if (competitionConfigRulesetVersion) competitionConfigRulesetVersion.addEventListener('change', () => {
    syncRulesetFromVersion(competitionConfigRulesetVersion, competitionConfigRuleset);
    updateCompetitionConfigVisibility();
  });
  const competitionDeckSearch = deckSearchNode();
  if (competitionDeckSearch) competitionDeckSearch.addEventListener('input', renderCompetitionDeckPicker);
  const competitionDeckAdd = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-add]') : null;
  if (competitionDeckAdd) {
    competitionDeckAdd.addEventListener('click', () => {
      if (competitionDeckRosterLocked()) return;
      const picker = deckPickerNode();
      const deckId = picker ? String(picker.value || '') : '';
      if (!deckId) return;
      if (!canAddParticipant(deckById(deckId))) {
        showParticipantRestriction(participantAddRestriction(deckById(deckId)));
        return;
      }
      if (effectiveSelectedCompetitionDeckIds().has(deckId)) {
        renderCompetitionDeckPicker();
        return;
      }
      useManualFormatSelection();
      competitionSelectedDeckIds.add(deckId);
      if (competitionDeckSearch) competitionDeckSearch.value = '';
      syncCompetitionDeckInputs();
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
    });
  }
  const competitionDecksOpen = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-decks-open]') : null;
  if (competitionDecksOpen) {
    competitionDecksOpen.addEventListener('click', () => {
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      if (competitionDecksModal) competitionDecksModal.show();
    });
  }
  function completedQualifierOptions() {
    const targetDivisionId = selectedCompetitionDivisionId();
    return (data.competitions || [])
      .filter((competition) => {
        if (String(competition.type || '') !== 'qualifier') return false;
        if (normalizeCompetitionStatus(competition.status) !== 'completed') return false;
        const ranking = Array.isArray(competition.ranking) ? competition.ranking : [];
        if (!ranking.length) return false;
        return !targetDivisionId || String(competition.division_id || '') === String(targetDivisionId);
      })
      .sort((a, b) => String(b.ends_on || b.starts_on || b.created_at || '').localeCompare(String(a.ends_on || a.starts_on || a.created_at || '')) || Number(b.id || 0) - Number(a.id || 0));
  }
  function selectedQualifierLoad() {
    return completedQualifierOptions().find((competition) => String(competition.id || '') === String(activeQualifierLoadId || '')) || null;
  }
  function renderQualifierLoadList() {
    if (!qualifierLoadModalNode) return;
    const body = qualifierLoadModalNode.querySelector('[data-qualifier-load-list]');
    if (!body) return;
    const query = String(qualifierLoadModalNode.querySelector('[data-qualifier-load-search]')?.value || '').trim().toLowerCase();
    const rows = completedQualifierOptions().filter((competition) => {
      const text = `${competition.name || ''} ${competition.code || ''} ${competition.division_code || ''} ${competition.ends_on || ''} ${competition.starts_on || ''}`.toLowerCase();
      return !query || text.includes(query);
    });
    body.innerHTML = rows.length ? rows.map((competition) => {
      const rankingCount = Array.isArray(competition.ranking) ? competition.ranking.length : 0;
      const selected = String(competition.id || '') === String(activeQualifierLoadId || '');
      return `
        <tr class="${selected ? 'active-row' : ''}">
          <td><b>${escapeHtml(competition.name || 'Clasificatorio')}</b><br><small>${escapeHtml(competition.code || '')}</small></td>
          <td>${escapeHtml(competition.division_code || competition.division || 'General')}</td>
          <td>${escapeHtml(competition.ends_on || competition.starts_on || 'Sin fecha')}</td>
          <td>${Number(competition.deck_pool_count || (competition.selected_deck_ids || []).length || rankingCount)}</td>
          <td>${rankingCount}</td>
          <td><button class="btn btn-outline-info btn-sm" type="button" data-qualifier-load-select="${escapeHtml(competition.id)}">Elegir</button></td>
        </tr>
      `;
    }).join('') : '<tr><td colspan="6">No hay clasificatorios finalizados con ranking para este formato.</td></tr>';
    body.querySelectorAll('[data-qualifier-load-select]').forEach((button) => {
      button.addEventListener('click', () => {
        activeQualifierLoadId = String(button.dataset.qualifierLoadSelect || '');
        const qualifier = selectedQualifierLoad();
        const fromInput = qualifierLoadModalNode.querySelector('[data-qualifier-load-from]');
        const toInput = qualifierLoadModalNode.querySelector('[data-qualifier-load-to]');
        if (fromInput) fromInput.value = '1';
        if (toInput) toInput.value = String(Math.min(4, Math.max(0, qualifier?.ranking?.length || 0)));
        renderQualifierLoadList();
        renderQualifierLoadPreview();
      });
    });
  }
  function qualifierLoadRange() {
    const qualifier = selectedQualifierLoad();
    const ranking = Array.isArray(qualifier?.ranking) ? qualifier.ranking : [];
    const fromInput = qualifierLoadModalNode?.querySelector('[data-qualifier-load-from]');
    const toInput = qualifierLoadModalNode?.querySelector('[data-qualifier-load-to]');
    const from = Math.max(1, Number.parseInt(String(fromInput?.value || '1'), 10) || 1);
    const to = Math.max(1, Number.parseInt(String(toInput?.value || '1'), 10) || 1);
    const start = Math.min(from, to);
    const end = Math.max(from, to);
    const selectedRows = ranking.slice(start - 1, end);
    return { qualifier, ranking, from: start, to: end, selectedRows };
  }
  function renderQualifierLoadPreview() {
    if (!qualifierLoadModalNode) return;
    const { qualifier, ranking, from, to, selectedRows } = qualifierLoadRange();
    const title = qualifierLoadModalNode.querySelector('[data-qualifier-load-title]');
    const meta = qualifierLoadModalNode.querySelector('[data-qualifier-load-meta]');
    const note = qualifierLoadModalNode.querySelector('[data-qualifier-load-note]');
    const preview = qualifierLoadModalNode.querySelector('[data-qualifier-load-preview]');
    const apply = qualifierLoadModalNode.querySelector('[data-qualifier-load-apply]');
    const rankingTable = qualifierLoadModalNode.querySelector('[data-qualifier-load-ranking]');
    if (title) title.textContent = qualifier ? qualifier.name || 'Clasificatorio' : 'Selecciona un clasificatorio';
    if (meta) meta.textContent = qualifier ? `${qualifier.division_code || qualifier.division || 'General'} | ${ranking.length} posiciones disponibles` : 'Solo se listan clasificatorios finalizados con ranking calculado.';
    const total = selectedRows.length;
    const deckIds = selectedRows.map((row) => String(row.id || '')).filter(Boolean);
    const missingFormat = deckIds.some((id) => !deckMatchesCompetitionFormat(deckById(id)));
    const valid = !!qualifier && total >= 4 && total % 2 === 0 && to <= ranking.length && !missingFormat;
    if (note) {
      note.textContent = !qualifier
        ? 'Elige un clasificatorio de la tabla.'
        : missingFormat
          ? 'El rango contiene decks de otro formato. Elige un clasificatorio del mismo formato.'
          : valid
            ? `Rango valido: ${from}-${to}, ${total} decks.`
            : `Rango invalido: debe ser par, minimo 4 y no superar ${ranking.length} posiciones.`;
      note.classList.toggle('text-warning', !!qualifier && !valid);
    }
    if (preview) {
      preview.innerHTML = selectedRows.length
        ? `<strong>${total} decks listos para cargar</strong><span>Posiciones ${from} a ${to}. Al cargar se reemplaza la seleccion actual y luego puedes agregar o quitar manualmente.</span>`
        : '<p class="note">Sin decks en el rango seleccionado.</p>';
    }
    if (rankingTable) {
      rankingTable.innerHTML = ranking.length ? ranking.map((row, index) => {
        const position = index + 1;
        const deck = deckById(row.id);
        const inRange = position >= from && position <= to;
        return `
          <tr class="${inRange ? 'active-row' : ''}">
            <td>#${position}</td>
            <td><b>${escapeHtml(row.name || deck?.name || 'Deck')}</b></td>
            <td>${escapeHtml(row.team || deck?.team || 'Sin equipo')}</td>
            <td>${escapeHtml(deck?.division || qualifier?.division_code || 'Formato')}</td>
            <td>${escapeHtml(row.pj ?? row.played ?? '-')}</td>
            <td>${escapeHtml(row.pts ?? row.points ?? '-')}</td>
          </tr>
        `;
      }).join('') : '<tr><td colspan="6"><div class="empty-state">Elige un clasificatorio para ver todas las posiciones.</div></td></tr>';
    }
    if (apply) apply.disabled = !valid;
  }
  const qualifierLoadOpen = competitionConfigModal ? competitionConfigModal.querySelector('[data-qualifier-load-open]') : null;
  if (qualifierLoadOpen) {
    qualifierLoadOpen.addEventListener('click', () => {
      if (!qualifierLoadModal) return;
      activeQualifierLoadId = '';
      const search = qualifierLoadModalNode.querySelector('[data-qualifier-load-search]');
      if (search) search.value = '';
      renderQualifierLoadList();
      renderQualifierLoadPreview();
      qualifierLoadModal.show();
    });
  }
  if (qualifierLoadModalNode) {
    qualifierLoadModalNode.querySelector('[data-qualifier-load-search]')?.addEventListener('input', renderQualifierLoadList);
    qualifierLoadModalNode.querySelector('[data-qualifier-load-from]')?.addEventListener('input', renderQualifierLoadPreview);
    qualifierLoadModalNode.querySelector('[data-qualifier-load-to]')?.addEventListener('input', renderQualifierLoadPreview);
    qualifierLoadModalNode.querySelector('[data-qualifier-load-apply]')?.addEventListener('click', () => {
      const { selectedRows } = qualifierLoadRange();
      if (selectedRows.some(row => participantOtherLeague(deckById(row.id))) || !compatibleParticipantRoster(selectedRows.map(row => deckById(row.id)))) {
        window.alert('No se pueden cargar decks de otro formato o asignados a otra liga.');
        return;
      }
      useManualFormatSelection();
      competitionSelectedDeckIds.clear();
      selectedRows.forEach((row) => {
        const id = String(row.id || '');
        if (id) competitionSelectedDeckIds.add(id);
      });
      syncCompetitionDeckInputs();
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
      if (qualifierLoadModal) qualifierLoadModal.hide();
      if (competitionDecksModal) competitionDecksModal.show();
    });
  }
  const competitionDeleteOpen = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-delete-open]') : null;
  if (competitionDeleteOpen) {
    competitionDeleteOpen.addEventListener('click', () => {
      if (competitionDeleteOpen.disabled || !deleteCompetitionModalNode) return;
      const idInput = deleteCompetitionModalNode.querySelector('[data-delete-competition-id]');
      const confirmInput = deleteCompetitionModalNode.querySelector('[data-delete-competition-confirmation]');
      const submitButton = deleteCompetitionModalNode.querySelector('[data-delete-competition-submit]');
      const meta = deleteCompetitionModalNode.querySelector('[data-delete-competition-meta]');
      if (idInput) idInput.value = competitionDeleteOpen.dataset.competitionId || '';
      if (confirmInput) confirmInput.value = '';
      if (submitButton) submitButton.disabled = true;
      if (meta) meta.textContent = `Eliminaras "${competitionDeleteOpen.dataset.competitionName || 'este evento'}" y sus datos asociados de la base de datos.`;
      if (deleteCompetitionModal) deleteCompetitionModal.show();
    });
  }
  const deleteCompetitionConfirmation = deleteCompetitionModalNode ? deleteCompetitionModalNode.querySelector('[data-delete-competition-confirmation]') : null;
  if (deleteCompetitionConfirmation) {
    deleteCompetitionConfirmation.addEventListener('input', () => {
      const submitButton = deleteCompetitionModalNode.querySelector('[data-delete-competition-submit]');
      if (submitButton) submitButton.disabled = deleteCompetitionConfirmation.value.trim().toLowerCase() !== 'eliminar';
    });
  }
  document.querySelectorAll('[data-competition-config-deck]').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      if (competitionDeckRosterLocked()) {
        syncCompetitionDeckInputs();
        return;
      }
      if (checkbox.checked && !canAddParticipant(deckById(checkbox.value))) {
        checkbox.checked = false;
        showParticipantRestriction(participantAddRestriction(deckById(checkbox.value)));
        return;
      }
      if (checkbox.checked) {
        competitionSelectedDeckIds.add(String(checkbox.value));
      } else {
        competitionSelectedDeckIds.delete(String(checkbox.value));
      }
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
    });
  });
