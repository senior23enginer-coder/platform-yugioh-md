/**
 * participant-picker: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function deckLabel(deck) {
    return `${deck.name || 'Deck'}${deck.team ? ` | ${deck.team}` : ''}${deck.division ? ` | ${deck.division}` : ''}`;
  }
  function selectedDeckListNode() {
    return competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-selected-decks]') : null;
  }
  function deckPickerNode() {
    return competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-picker]') : null;
  }
  function deckSearchNode() {
    return competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-search]') : null;
  }
  function competitionDeckRosterLocked() {
    if (!competitionConfigModal) return false;
    return competitionConfigModal.dataset.deckRosterLocked === '1';
  }
  function competitionCanEditRoster(competition) {
    return window.StationXParticipantPolicy.competitionCanEditRoster(competition, currentCompetitionType, normalizeStatusKey);
  }
  function syncCompetitionStatusOptions(statusSelect, currentStatus) {
    if (!statusSelect) return;
    const normalized = normalizeCompetitionStatus(currentStatus);
    Array.from(statusSelect.options).forEach((option) => {
      const value = option.value;
      const allowed = normalized === 'cancelled'
        ? ['cancelled', 'resume'].includes(value)
        : [normalized, 'cancelled'].includes(value);
      option.hidden = !allowed;
      option.disabled = !allowed;
    });
    if (normalized === 'cancelled') {
      statusSelect.value = 'cancelled';
    } else if (statusSelect.value === 'resume') {
      statusSelect.value = normalized;
    } else {
      statusSelect.value = normalized;
    }
  }
  function updateCompetitionCancelledLock(currentStatus) {
    if (!competitionConfigModal) return;
    const cancelled = competitionIsCancelled(currentStatus);
    const statusSelect = competitionConfigModal.querySelector('[data-competition-config-status]');
    const statusNote = competitionConfigModal.querySelector('[data-competition-status-note]');
    competitionConfigModal.dataset.eventCancelled = cancelled ? '1' : '0';
    if (statusNote) {
      statusNote.textContent = cancelled
        ? 'Evento anulado: no se puede editar. Selecciona Reanudar y guarda para volver a Pendiente configuracion.'
        : '';
    }
    competitionConfigModal.querySelectorAll('.modal-body input, .modal-body select, .modal-body textarea').forEach((control) => {
      if (control.type === 'hidden' || control === statusSelect) return;
      control.disabled = control.dataset.lockedAlways === '1' || cancelled || control.dataset.lockedByStart === '1';
    });
    if (statusSelect) statusSelect.disabled = false;
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="save_schedule"], [data-competition-config-submit="schedule_qualifier"], [data-competition-config-submit="schedule_cup"], [data-competition-config-submit="start"]').forEach((button) => {
      button.disabled = cancelled || button.disabled;
    });
  }
  function syncCompetitionDeckInputs() {
    if (!competitionConfigModal) return;
    competitionConfigModal.querySelectorAll('[data-competition-config-deck]').forEach((checkbox) => {
      checkbox.checked = competitionSelectedDeckIds.has(String(checkbox.value));
    });
  }
  function selectedCompetitionDivisionId() {
    const value = String(competitionConfigModal?.querySelector('[data-competition-config-division]')?.value || '');
    return value.startsWith('format:') ? '' : value;
  }
  function selectedCompetitionDeckPool() {
    return String(competitionConfigModal?.querySelector('[data-competition-config-deck-pool]')?.value || 'all_active');
  }
  function selectedCompetitionParticipantsPerSide() {
    return competitionParticipantsPerSide(competitionConfigModal?.querySelector('[data-competition-config-match]'));
  }
  function selectedCompetitionDecks() {
    return (data.allDecks || []).filter((deck) => competitionSelectedDeckIds.has(String(deck.id || '')));
  }
  function effectiveSelectedCompetitionDeckIds() {
    const ids = new Set(Array.from(competitionSelectedDeckIds).map((id) => String(id)));
    if (!competitionConfigModal) return ids;
    competitionConfigModal.querySelectorAll('[data-competition-config-deck]:checked').forEach((checkbox) => {
      ids.add(String(checkbox.value || ''));
    });
    competitionConfigModal.querySelectorAll('[data-selected-competition-deck-id]').forEach((row) => {
      ids.add(String(row.dataset.selectedCompetitionDeckId || ''));
    });
    ids.delete('');
    return ids;
  }
  function deckById(deckId) {
    return (data.allDecks || []).find((deck) => String(deck.id || '') === String(deckId || '')) || null;
  }
  function participantFormatCodes(deck) {
    return window.StationXParticipantPolicy.participantFormatCodes(deck);
  }
  function participantFormatLabel(deck) {
    return participantFormatCodes(deck).map(code => rulesetLabel(code)).join(' / ') || 'Sin formato';
  }
  function compatibleParticipantRoster(decks) {
    return window.StationXParticipantPolicy.compatibleParticipantRoster(decks, competitionConfigModal?.querySelector('[data-competition-config-ruleset]')?.value || '');
  }
  function deckMatchesCompetitionFormat(deck) {
    return compatibleParticipantRoster([deck]);
  }
  function showParticipantRestriction(message = '') {
    const note = competitionConfigModal?.querySelector('[data-competition-format-restriction]');
    if (!note) return;
    const eventFormat = competitionConfigModal.querySelector('[data-competition-config-ruleset]')?.value || '';
    const selected = selectedCompetitionDecks();
    const selectedFormats = selected.length ? participantFormatLabel(selected[0]) : '';
    const allowed = eventFormat && eventFormat !== 'custom' ? rulesetLabel(eventFormat) : selectedFormats;
    note.textContent = message || `No se pueden mezclar decks de distintos formatos.${allowed ? ' Formato permitido: ' + allowed + '.' : ' El primer deck define el formato de los participantes.'} Solo se muestran decks compatibles y disponibles.`;
    if (!message && currentCompetitionConfig()?.type === 'league') note.textContent += ' Un deck no puede estar en dos ligas de la misma temporada; si puede participar en copas y otros eventos.';
    note.classList.toggle('text-warning', Boolean(message));
  }
  function participantOtherLeague(deck) {
    return window.StationXParticipantPolicy.participantOtherLeague(deck, currentCompetitionConfig, data);
  }
  function canAddParticipant(deck) {
    return participantIsQualified(deck) && !participantOtherLeague(deck) && compatibleParticipantRoster([...selectedCompetitionDecks(), deck]);
  }
  function participantIsQualified(deck) {
    return window.StationXParticipantPolicy.participantIsQualified(deck, currentCompetitionConfig);
  }
  function participantAddRestriction(deck) {
    return window.StationXParticipantPolicy.participantAddRestriction(deck, participantIsQualified, participantOtherLeague);
  }
  function leagueMovementDivisions(competition = currentCompetitionConfig()) {
    if (!competition || competition.type !== 'league') return [];
    return Object.values(data.divisions || {}).filter((division) => String(division.league_competition_id || '') === String(competition.id)).sort((a,b) => Number(a.tier_order || 0)-Number(b.tier_order || 0) || Number(a.id)-Number(b.id));
  }
  function renderLeagueMovementSheet(competition) {
    const modal = document.querySelector('[data-league-movement-modal]');
    if (!modal) return;
    const divisions = leagueMovementDivisions(competition);
    modal.querySelector('[data-league-movement-summary]').textContent = competition.name || '';
    const rows = modal.querySelector('[data-league-movement-rows]');
    if (divisions.length < 2) { rows.innerHTML = ''; return; }
    const fields = divisions.map(division => ['promotion','relegation'].map(kind => `<input type="hidden" name="division_movements[${escapeHtml(division.id)}][${kind}_slots]" value="${Number(division[kind+'_slots'] || 0)}"><input type="hidden" name="division_movements[${escapeHtml(division.id)}][${kind}_to_division_id]" value="${escapeHtml(division[kind+'_to_division_id'] || '')}">`).join('')).join('');
    rows.innerHTML = fields + divisions.slice(0,-1).map((upper,index) => {
      const lower=divisions[index+1];
      const down=Number(upper.relegation_slots || 0),up=Number(lower.promotion_slots || 0);
      const balanced=down===up;
      const max=Math.min((upper.decks || []).length,(lower.decks || []).length);
      return `<section class="league-movement-division"><h3>${escapeHtml(upper.name)} &harr; ${escapeHtml(lower.name)}</h3><label>Decks que bajan y suben (misma cantidad)<input class="form-control" type="number" min="0" max="${max}" step="1" required value="${balanced ? down : ''}" data-division-exchange="${index}" aria-label="Cupos de intercambio entre ${escapeHtml(upper.name)} y ${escapeHtml(lower.name)}"></label><p class="note mt-2" data-exchange-description="${index}"></p>${balanced ? '' : '<p class="text-warning">La configuracion anterior tenia cantidades distintas. Indica una cantidad igual para ambos movimientos.</p>'}</section>`;
    }).join('');
    const setField=(id,key,value) => { const input=rows.querySelector(`[name="division_movements[${id}][${key}]"]`); if(input) input.value=String(value); };
    // End divisions have no destination beyond their boundary.
    setField(divisions[0].id,'promotion_slots',0);setField(divisions[0].id,'promotion_to_division_id','');
    setField(divisions.at(-1).id,'relegation_slots',0);setField(divisions.at(-1).id,'relegation_to_division_id','');
    rows.querySelectorAll('[data-division-exchange]').forEach(input => {
      const sync=() => {
        const index=Number(input.dataset.divisionExchange),upper=divisions[index],lower=divisions[index+1];
        const value=Number(input.value);
        rows.querySelector(`[data-exchange-description="${index}"]`).textContent=input.value==='' ? 'Define cuantos decks intercambian estas divisiones.' : `Bajan ${value} de ${upper.name} y suben ${value} de ${lower.name}.`;
        if(input.value==='' || !input.validity.valid) return;
        setField(upper.id,'relegation_slots',value);setField(upper.id,'relegation_to_division_id',value ? lower.id : '');
        setField(lower.id,'promotion_slots',value);setField(lower.id,'promotion_to_division_id',value ? upper.id : '');
      };
      input.addEventListener('input',sync);sync();
    });
  }

  function useManualFormatSelection() {
    const pool = competitionConfigModal?.querySelector('[data-competition-config-deck-pool]');
    if (pool) pool.value = 'all_active';
    syncCompetitionDeckPoolNote();
  }
  function refreshFormatParticipants() {
    if (selectedCompetitionDeckPool() === 'division' && !competitionDeckRosterLocked()) {
      const candidates = (data.allDecks || []).filter((deck) => deckMatchesCompetitionFormat(deck) && !participantOtherLeague(deck));
      if (!compatibleParticipantRoster(candidates)) {
        useManualFormatSelection();
        showParticipantRestriction('Elige un formato especifico antes de incluir todos sus decks. No se permite mezclar formatos.');
        return;
      }
      competitionSelectedDeckIds = new Set(candidates.map((deck) => String(deck.id)));
    }
    syncCompetitionDeckInputs();
    syncCompetitionDeckPoolNote();
    renderCompetitionSelectedDecks();
    renderCompetitionDeckPicker();
    updateCompetitionDeckConstraints();
  }
  document.querySelector('[data-league-movement-open]')?.addEventListener('click', () => {
    if (leagueMovementDivisions().length < 2) return;
    bootstrap.Modal.getOrCreateInstance(document.querySelector('[data-league-movement-modal]')).show();
  });
  function currentCompetitionType() {
    return competitionConfigModal ? String(competitionConfigModal.querySelector('[data-competition-config-type]')?.value || '') : '';
  }
  function eligibleTeamIdsForCompetition(divisionId, participantsPerSide) {
    return window.StationXParticipantPolicy.eligibleTeamIdsForCompetition(divisionId, participantsPerSide, data, deckMatchesCompetitionFormat);
  }
  function renderCompetitionDeckPicker() {
    const picker = deckPickerNode();
    if (!picker) return;
    showParticipantRestriction();
    const locked = competitionDeckRosterLocked();
    const search = deckSearchNode();
    const addButton = competitionConfigModal ? competitionConfigModal.querySelector('[data-competition-deck-add]') : null;
    if (search) search.disabled = locked;
    if (addButton) addButton.disabled = locked;
    if (locked) {
      picker.disabled = true;
      picker.innerHTML = '<option value="">Roster cerrado por evento iniciado</option>';
      return;
    }
    picker.disabled = false;
    const searchValue = (deckSearchNode()?.value || '').trim().toLowerCase();
    const divisionId = selectedCompetitionDivisionId();
    const participantsPerSide = selectedCompetitionParticipantsPerSide();
    const eligibleTeamIds = eligibleTeamIdsForCompetition(divisionId, participantsPerSide);
    const selectedDeckIds = effectiveSelectedCompetitionDeckIds();
    const availableDecks = (data.allDecks || []).filter((deck) => {
      const id = String(deck.id || '');
      if (!id || !deck.team_id || selectedDeckIds.has(id) || !canAddParticipant(deck)) return false;
      return !searchValue || `${deckLabel(deck)} ${participantFormatLabel(deck)}`.toLowerCase().includes(searchValue);
    });
    const allowed = deck => canAddParticipant(deck) && (!eligibleTeamIds || eligibleTeamIds.has(String(deck.team_id || '')));
    const groups = new Map();
    availableDecks.forEach(deck => {
      const label = participantFormatLabel(deck);
      if (!groups.has(label)) groups.set(label, []);
      groups.get(label).push(deck);
    });
    const hasAvailable = availableDecks.some(allowed);
    picker.innerHTML = (hasAvailable ? '' : '<option value="">No hay mas decks compatibles disponibles</option>') + [...groups].map(([label, decks]) => `<optgroup label="${escapeHtml(label)}">${decks.map(deck => `<option value="${escapeHtml(deck.id)}" ${allowed(deck) ? '' : 'disabled'}>${escapeHtml(deckLabel(deck))} - ${escapeHtml(label)}${canAddParticipant(deck) ? (allowed(deck) ? '' : ' - Equipo incompleto para este match') : ' - No permitido: formato diferente'}</option>`).join('')}</optgroup>`).join('');
    if (hasAvailable) picker.value = String(availableDecks.find(allowed).id);
    if (addButton) addButton.disabled = !hasAvailable;
    showParticipantRestriction();
  }

  function renderCompetitionSelectedDecks() {
    const list = selectedDeckListNode();
    if (!list) return;
    const locked = competitionDeckRosterLocked();
    const selectedDecks = selectedCompetitionDecks();
    const divisionId = selectedCompetitionDivisionId();
    const competition = currentCompetitionConfig();
    const isLeague = competition?.type === 'league';
    const groups = new Map();
    if (isLeague) leagueMovementDivisions(competition).forEach(division => groups.set(String(division.id), { name: division.name, decks: [] }));
    selectedDecks.forEach(deck => {
      const key = isLeague ? String(deck.division_id || 'none') : 'all';
      if (!groups.has(key)) {
        const division = Object.values(data.divisions || {}).find(division => String(division.id) === key);
        groups.set(key, { name: division?.name || (isLeague ? 'Sin division asignada' : 'Participantes'), decks: [] });
      }
      groups.get(key).decks.push(deck);
    });
    const registered = new Set(selectedDecks.map(deck => String(deck.id)));
    const viewGroups = new Map([...groups].map(([id, group]) => [id, { name: group.name, decks: [...group.decks] }]));
    const ownedIds = new Set(leagueMovementDivisions(competition).map(division => String(division.id)));
    (data.allDecks || []).forEach(deck => {
      const key = String(deck.division_id || 'none');
      if (!isLeague || !ownedIds.has(key)) return;
      const group = viewGroups.get(key);
      if (!group.decks.some(item => String(item.id) === String(deck.id))) group.decks.push(deck);
    });
    if (participantDivisionEvent !== String(competition?.id || '')) {
      participantDivisionEvent = String(competition?.id || '');
      participantDivisionView = '';
    }
    if (!viewGroups.has(participantDivisionView)) participantDivisionView = viewGroups.keys().next().value || '';
    const navigation = competitionConfigModal.querySelector('[data-participant-division-buttons]');
    if (navigation) {
      navigation.hidden = !isLeague;
      navigation.innerHTML = isLeague ? [...viewGroups].map(([id, group]) => `<button type="button" class="participant-division-button ${id === participantDivisionView ? 'is-active' : ''}" aria-pressed="${id === participantDivisionView}" data-participant-division-open="${escapeHtml(id)}"><strong>${escapeHtml(group.name)}</strong><span>${group.decks.length} decks - ${group.decks.filter(deck => registered.has(String(deck.id))).length} inscritos</span></button>`).join('') : '';
      navigation.querySelectorAll('[data-participant-division-open]').forEach(button => button.addEventListener('click', () => {
        participantDivisionView = button.dataset.participantDivisionOpen;
        renderCompetitionSelectedDecks();
        list.closest('.selected-deck-table-wrap')?.scrollTo({ top: 0 });
      }));
    }
    const modalTitle = competitionConfigModal.querySelector('[data-participant-modal-title]');
    if (modalTitle) modalTitle.textContent = isLeague ? 'Divisiones y decks' : 'Decks participantes';
    const openButton = competitionConfigModal.querySelector('[data-competition-decks-open]');
    if (openButton) openButton.innerHTML = `<i class="bi bi-collection"></i> ${isLeague ? 'Divisiones y decks' : 'Decks participantes'}`;
    const visibleGroups = isLeague ? [...viewGroups].filter(([id]) => id === participantDivisionView) : [...groups];
    const renderDeckRow = (deck, index) => {
      const enrolled = registered.has(String(deck.id));
      const allowed = canAddParticipant(deck);
      return `<tr class="${deckMatchesCompetitionFormat(deck, divisionId) ? '' : 'is-invalid'}" data-division-deck-id="${escapeHtml(deck.id)}" ${enrolled ? `data-selected-competition-deck-id="${escapeHtml(deck.id)}"` : ''}>
        <td data-label="#">${index + 1}</td>
        <td data-label="Deck"><b>${escapeHtml(deck.name || 'Deck')}</b>${isLeague ? `<small class="d-block">${enrolled ? 'Inscrito' : 'Sin inscribir'}</small>` : ''}</td>
        <td data-label="Equipo">${escapeHtml(deck.team || 'Sin equipo')}</td>
        <td data-label="Formato">${escapeHtml(participantFormatLabel(deck))}</td>
        <td data-label="Accion">${locked ? '<span class="status-chip">Roster cerrado</span>' : enrolled ? `<button type="button" class="btn btn-outline-light btn-sm" data-competition-deck-remove="${escapeHtml(deck.id)}"><i class="bi bi-x-lg"></i> Quitar</button>` : `<button type="button" class="btn btn-outline-info btn-sm" data-division-deck-enroll="${escapeHtml(deck.id)}" ${allowed ? '' : 'disabled title="No se permite mezclar formatos"'}><i class="bi bi-plus-circle"></i> Inscribir</button>`}</td>
      </tr>`;
    };
    list.innerHTML = visibleGroups.map(([, group]) => group.decks.length ? group.decks.map(renderDeckRow).join('') : '<tr><td colspan="5">Sin decks asignados a esta division.</td></tr>').join('') || '<tr><td colspan="5"><div class="empty-state">No hay decks seleccionados para este evento.</div></td></tr>';
    list.querySelectorAll('[data-division-deck-enroll]').forEach(button => button.addEventListener('click', () => {
      if (competitionDeckRosterLocked()) return;
      const id = String(button.dataset.divisionDeckEnroll);
      if (!canAddParticipant(deckById(id))) { showParticipantRestriction(participantAddRestriction(deckById(id))); return; }
      useManualFormatSelection();
      competitionSelectedDeckIds.add(id);
      syncCompetitionDeckInputs();
      renderCompetitionSelectedDecks();
      renderCompetitionDeckPicker();
      updateCompetitionConfigCount();
      updateCompetitionDeckConstraints();
    }));
    list.querySelectorAll('[data-competition-deck-remove]').forEach((button) => {
      button.addEventListener('click', () => {
        if (competitionDeckRosterLocked()) return;
        useManualFormatSelection();
        competitionSelectedDeckIds.delete(String(button.dataset.competitionDeckRemove || ''));
        syncCompetitionDeckInputs();
        renderCompetitionSelectedDecks();
        renderCompetitionDeckPicker();
        updateCompetitionConfigCount();
      });
    });
  }
  function updateCompetitionConfigCount() {
    const checked = competitionSelectedDeckIds.size || document.querySelectorAll('[data-competition-config-deck]:checked').length;
    if (competitionConfigCount) competitionConfigCount.textContent = `${checked} seleccionados`;
  }
  function syncCompetitionDeckPoolNote() {
    if (!competitionConfigModal) return;
    const note = competitionConfigModal.querySelector('[data-competition-deck-pool-note]');
    if (!note) return;
    const deckPool = selectedCompetitionDeckPool();
    const format = competitionConfigModal.querySelector('[data-competition-config-ruleset]');
    const label = format?.selectedOptions?.[0]?.textContent?.trim() || 'seleccionado';
    const count = (data.allDecks || []).filter((deck) => deckMatchesCompetitionFormat(deck) && !participantOtherLeague(deck)).length;
    note.textContent = deckPool === 'division'
      ? `Se incluiran los ${count} decks del formato ${label}.`
      : `Selecciona participantes entre los ${count} decks del formato ${label}.`;
  }
  function currentCompetitionId() {
    return competitionConfigModal ? String(competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '') : '';
  }
  function competitionScheduledMatchCount(competitionId = currentCompetitionId()) {
    if (!competitionId) return 0;
    return allMatchList().filter((match) =>
      String(match.competition_id || '') === String(competitionId)
      && match.scheduled_on
      && !['FINALIZADA', 'completed', 'finalizada'].includes(String(match.estado || match.status || ''))
    ).length;
  }
  function competitionPersistedMatchCount(competitionId = currentCompetitionId()) {
    if (!competitionId) return 0;
    return allMatchList().filter((match) => String(match.competition_id || '') === String(competitionId)).length;
  }
  function competitionUsesWorkflow() {
    const competition = (data.competitions || []).find(c => String(c.id) === String(currentCompetitionId()));
    return !!(data.executionCapabilities || {})[competition?.execution_model]?.policy;
  }
  function expectedQualifierMatchCount(deckCount, participantsPerSide, duelsPerDeck) {
    return window.StationXParticipantPolicy.expectedQualifierMatchCount(deckCount, participantsPerSide, duelsPerDeck, competitionUsesWorkflow);
  }
  function currentEligibleGroupCount(participantsPerSide = selectedCompetitionParticipantsPerSide()) {
    return window.StationXParticipantPolicy.currentEligibleGroupCount(participantsPerSide, selectedCompetitionDivisionId, selectedCompetitionDecks, deckMatchesCompetitionFormat);
  }
  function competitionParticipantsPerSide(matchSelect) {
    const selected = matchSelect?.selectedOptions?.[0];
    const configured = Number(selected?.dataset?.participants || 0);
    if (configured > 0) return Math.min(3, Math.max(1, configured));
    return Math.min(3, Math.max(1, Number.parseInt(String(matchSelect?.value || '1v1'), 10) || 1));
  }
  function updateCompetitionDeckConstraints() {
    if (!competitionConfigModal) return true;
    const currentStatus = normalizeCompetitionStatus(competitionConfigModal.querySelector('[data-competition-config-status]')?.value || '');
    const isActiveEvent = currentStatus === 'active';
    if (competitionConfigModal.dataset.eventCancelled === '1') {
      competitionConfigModal.querySelectorAll('[data-competition-config-submit="schedule_qualifier"], [data-competition-config-submit="schedule_cup"], [data-competition-config-submit="start"]').forEach((button) => {
        button.disabled = true;
      });
      competitionConfigModal.querySelectorAll('[data-requires-active-event]').forEach((button) => {
        button.disabled = true;
      });
      return false;
    }
    const typeSelect = competitionConfigModal.querySelector('[data-competition-config-type]');
    const matchSelect = competitionConfigModal.querySelector('[data-competition-config-match]');
    const startInput = competitionConfigModal.querySelector('[data-competition-config-start]');
    const qualifierDuelsSelect = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels]');
    const qualifierWrap = competitionConfigModal.querySelector('[data-competition-config-qualifier-duels-wrap]');
    const isQualifier = (typeSelect?.value || '') === 'qualifier';
    const isCup = ['cup', 'final'].includes(typeSelect?.value || '');
    const isLeague = (typeSelect?.value || '') === 'league';
    const selectedDeckCount = competitionSelectedDeckIds.size;
    const divisionId = selectedCompetitionDivisionId();
    const selectedValidDecks = selectedCompetitionDecks().filter((deck) => deckMatchesCompetitionFormat(deck, divisionId));
    if (startInput) {
      const savedStartDate = String(startInput.dataset.savedValue || '');
      const keepsSavedPastDate = !!savedStartDate && startInput.value === savedStartDate && savedStartDate < serverToday;
      startInput.min = keepsSavedPastDate ? savedStartDate : serverToday;
      const invalidPastDate = !!startInput.value && startInput.value < serverToday && !keepsSavedPastDate;
      startInput.setCustomValidity(invalidPastDate ? 'La fecha de inicio no puede ser menor a la fecha actual.' : '');
    }
    if (matchSelect) {
      Array.from(matchSelect.options).forEach((option) => {
        const participants = Number(option.dataset.participants || Number.parseInt(option.value || '1', 10) || 1);
        const minimumDecksForOption = Math.max(4, participants * 2);
        const eligibleTeams = eligibleTeamIdsForCompetition(divisionId, participants);
        const eligibleGroupCount = participants > 1 ? (eligibleTeams ? eligibleTeams.size : 0) : selectedValidDecks.length;
        option.disabled = (isQualifier || isCup) && selectedDeckCount > 0 && (selectedValidDecks.length < minimumDecksForOption || eligibleGroupCount < 2);
      });
      if (matchSelect.selectedOptions[0]?.disabled) {
        const fallback = Array.from(matchSelect.options).find((option) => !option.disabled);
        if (fallback) matchSelect.value = fallback.value;
      }
    }
    const participantsPerSide = competitionParticipantsPerSide(matchSelect);
    const selectedTeamCounts = selectedValidDecks.reduce((map, deck) => {
      const teamId = String(deck.team_id || '');
      if (!teamId) return map;
      map[teamId] = (map[teamId] || 0) + 1;
      return map;
    }, {});
    const explicitTeamConfig=(data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))?.teamConfig;
    const groupCount = explicitTeamConfig ? explicitTeamConfig.lineups.length : participantsPerSide > 1
      ? Object.values(selectedTeamCounts).filter((count) => count >= participantsPerSide).length
      : selectedValidDecks.length;
    const maxDuels = Math.max(0, groupCount - 1);
    const minimumQualifierDecks = Math.max(4, participantsPerSide * 2);
    const selectedDuelsPerDeck = Number(qualifierDuelsSelect?.value || 0);
    const expectedMatches = expectedQualifierMatchCount(groupCount * participantsPerSide, participantsPerSide, selectedDuelsPerDeck);
    const scheduledMatches = competitionScheduledMatchCount();
    const persistedMatches = competitionPersistedMatchCount();
    const programmingButton = competitionConfigModal.querySelector('[data-event-programming-open]');
    if (programmingButton) {
      programmingButton.hidden = persistedMatches > 0;
      programmingButton.innerHTML = '<i class="bi bi-calendar2-week"></i> Programar';
      programmingButton.title = 'Define la fecha y genera los duelos del evento.';
    }
    const activeWithoutMatches = isActiveEvent && scheduledMatches === 0;
    if (qualifierDuelsSelect) {
      Array.from(qualifierDuelsSelect.options).forEach((option) => {
        const value = Number(option.value || 0);
        option.disabled = isQualifier && selectedDeckCount > 0 && (maxDuels < 2 || value > maxDuels);
      });
      if (qualifierDuelsSelect.selectedOptions[0]?.disabled) {
        const fallback = Array.from(qualifierDuelsSelect.options).filter((option) => !option.disabled).pop();
        if (fallback) qualifierDuelsSelect.value = fallback.value;
      }
    }
    const note = qualifierWrap ? qualifierWrap.querySelector('small') : null;
    if (qualifierWrap && competitionUsesWorkflow()) qualifierWrap.hidden = true;
    if (note && isQualifier) {
      note.textContent = selectedDeckCount
        ? `Decks validos: ${selectedValidDecks.length}/${selectedDeckCount}. Equipos aptos: ${groupCount}. Minimo requerido: ${minimumQualifierDecks}. Maximo posible: ${maxDuels >= 2 ? `${maxDuels} duelos por deck` : 'faltan equipos/decks para clasificar'}.`
        : 'Agrega minimo 4 decks para habilitar match y numero de duelos.';
    }
    const savedStartDate = String(startInput?.dataset?.savedValue || '');
    const scheduleDateValid = !startInput || !startInput.value || startInput.value >= serverToday || startInput.value === savedStartDate;
    const startDateReached = !startInput || !startInput.value || startInput.value <= serverToday;
    const workflowModel = (data.competitions || []).find(c => String(c.id) === String(currentCompetitionId()))?.execution_model;
    const savedTeamConfig=(data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))?.teamConfig;
    const needsTeamLineup=Number((data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))?.participants_per_side||1)>1 && ['tag_duel','team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship','all_star_event'].includes(((c)=>(data.eventPromptTemplates||[]).find(t=>t.code===c?.prompt_template)?.base_template||c?.prompt_template)((data.competitions || []).find(c=>String(c.id)===String(currentCompetitionId()))));
    const hasValidWorkflowRoster = needsTeamLineup ? !!savedTeamConfig && savedTeamConfig.lineups.length>=2 && savedTeamConfig.lineups.length<=64 : !competitionUsesWorkflow() || (participantsPerSide === 1 && selectedValidDecks.length <= 64 && (workflowModel !== 'series' || selectedValidDecks.length === 2));
    const validSchedule = hasValidWorkflowRoster && scheduleDateValid && (!isQualifier || (selectedValidDecks.length >= minimumQualifierDecks && groupCount >= 2 && maxDuels >= 2 && selectedDuelsPerDeck <= maxDuels && expectedMatches > 0));
    const needsEvenRoster = ['cup', 'tournament'].includes(typeSelect?.value || '');
    const hasEvenRoster = savedTeamConfig ? savedTeamConfig.lineups.length>=2 : selectedValidDecks.length >= 4 && selectedValidDecks.length % 2 === 0;
    const hasRequiredDivision = !isLeague || !!divisionId;
    const validStart = hasValidWorkflowRoster && startDateReached && hasRequiredDivision && (!isQualifier || (selectedValidDecks.length >= minimumQualifierDecks && groupCount >= 2 && maxDuels >= 2 && selectedDuelsPerDeck <= maxDuels && expectedMatches > 0)) && (!needsEvenRoster || hasEvenRoster) && (!isQualifier || scheduledMatches >= expectedMatches);
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="save_schedule"]').forEach((button) => {
      button.hidden = persistedMatches > 0;
      button.disabled = persistedMatches > 0 || isActiveEvent || !scheduleDateValid;
      button.title = scheduleDateValid ? 'Guardar fecha, tipo de match, frecuencia y regla.' : 'Selecciona una fecha valida.';
    });
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="schedule_qualifier"]').forEach((button) => {
      button.hidden = !isQualifier || persistedMatches > 0;
      button.disabled = persistedMatches > 0 || isActiveEvent || !isQualifier || !validSchedule;
    });
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="schedule_cup"]').forEach((button) => {
      button.hidden = !isCup || persistedMatches > 0;
      button.disabled = !hasValidWorkflowRoster || !isCup || isActiveEvent || scheduledMatches > 0 || !scheduleDateValid || !hasEvenRoster || groupCount < 2;
      button.title = scheduledMatches > 0
        ? 'La copa ya fue programada y no puede volver a generar su llave.'
        : (isCup && !hasEvenRoster
          ? `La copa necesita cantidad par de decks. Ahora tiene ${selectedValidDecks.length}; quita 1 deck para programarla.`
          : '');
      button.innerHTML = scheduledMatches > 0
        ? '<i class="bi bi-lock"></i> Copa programada'
        : '<i class="bi bi-trophy"></i> Programar copa';
    });
    competitionConfigModal.querySelectorAll('[data-competition-config-submit="start"]').forEach((button) => {
      button.disabled = isActiveEvent || !validStart;
      button.title = !startDateReached
        ? `Este evento se podra comenzar el ${startInput?.value || 'dia programado'}.`
        : (!hasRequiredDivision
          ? 'Selecciona la division asociada a esta liga.'
          : (isCup && !hasEvenRoster
          ? `No se puede comenzar con ${selectedValidDecks.length} decks. Quita 1 deck desde "Decks participantes".`
          : ''));
    });
    const competitionId = competitionConfigModal.querySelector('[data-competition-config-id]')?.value || '';
    const competition = (data.competitions || []).find((item) => String(item.id || '') === String(competitionId || '')) || {};
    const statusAllowsRead = !!competitionId && !['cancelled', 'archived', 'anulado'].includes(currentStatus);
    const isCompletedEvent = ['completed', 'finalizado', 'finalizada'].includes(currentStatus);
    const executionModel = String(competition.execution_model || '').toLowerCase();
    const phaseStructure = String(competition.phase_structure || '').toUpperCase();
    const simpleDivisionQualifier = isQualifier
      && !competitionUsesWorkflow()
      && executionModel === 'swiss'
      && (!phaseStructure || phaseStructure === 'NONE')
      && String(competition.event_flow || '') === 'QUALIFIES_TO_EVENT'
      && String(competition.advancement_rule || '') === 'RANKING_THRESHOLD';
    const linkedDivisions = Object.values(data.divisions || {}).filter((division) => String(division.source_qualifier_id || '') === String(competitionId || ''));
    const supportsAdvancePhase = (
      !!(data.executionCapabilities || {})[executionModel]?.policy
      ||
      ['single_elimination', 'double_elimination', 'bracket', 'series'].includes(executionModel)
      || ['cup', 'final', 'world'].includes(typeSelect?.value || '')
      || ['swiss_top_cut', 'top_cut'].includes(executionModel)
      || phaseStructure.includes('TOP_CUT')
      || executionModel === 'groups_knockout'
      || phaseStructure.includes('GROUPS')
      || ((typeSelect?.value || '') === 'league' && ['season_playoffs', 'playoffs'].includes(executionModel))
    );
    const canAdvancePhase = isActiveEvent
      && !simpleDivisionQualifier
      && supportsAdvancePhase;
    const canApplyDivisions = isCompletedEvent && isQualifier && linkedDivisions.length > 0;
    const canApplyLeagueMoves = isCompletedEvent
      && (typeSelect?.value || '') === 'league'
      && Number(competition.division_count || 0) > 1
      && String(competition.lock_division_moves || '0') !== '1'
      && leagueMovementDivisions(competition).some((division) => Number(division.promotion_slots || 0) > 0 || Number(division.relegation_slots || 0) > 0);
    const teamMode = Number(competition.participants_per_side||1)>1 && ['tag_duel','team_ycs','ots_tournament_series','team_3v3','team_league','team_cup','team_championship','all_star_event'].includes((data.eventPromptTemplates||[]).find(t=>t.code===competition.prompt_template)?.base_template||competition.prompt_template);
    competitionConfigModal.querySelectorAll('[data-open-team-lineups], [data-open-team-matches]').forEach(button => {button.hidden=!teamMode;});
    competitionConfigModal.querySelectorAll('[data-competition-decks-open]').forEach(button => {button.hidden=teamMode;});
    competitionConfigModal.querySelectorAll('[data-open-event-recovery]').forEach((button) => {
      const workflow = competition.workflow;
      button.hidden = !(workflow && !workflow.complete && workflow.round > 1 && workflow.history?.length && workflow.pending?.length && !isCompletedEvent);
      button.disabled = button.hidden;
    });
    competitionConfigModal.querySelectorAll('[data-competition-action-submit]').forEach((button) => {
      const action = button.dataset.competitionActionSubmit || '';
      const competitionType = typeSelect?.value || '';
      const visibleForType = action === 'apply_qualifier_divisions'
        ? isQualifier
        : (action === 'apply_league_movements'
          ? competitionType === 'league' && Number(competition.division_count || 0) > 1
          : (action === 'advance_competition'
            ? supportsAdvancePhase && !simpleDivisionQualifier
            : (action === 'recalculate_competition_standings' ? !['cup', 'final'].includes(competitionType) : true)));
      button.hidden = !visibleForType;
      if (!visibleForType) {
        button.disabled = true;
        return;
      }
      if (action === 'recalculate_competition_standings') {
        button.disabled = !statusAllowsRead;
      } else if (action === 'advance_competition') {
        button.disabled = !canAdvancePhase;
      } else if (action === 'apply_qualifier_divisions') {
        button.disabled = !canApplyDivisions;
      } else if (action === 'apply_league_movements') {
        button.disabled = !canApplyLeagueMoves;
      } else {
        button.disabled = !isActiveEvent;
      }
    });
    competitionConfigModal.querySelectorAll('[data-requires-active-event]:not([data-competition-action-submit])').forEach((button) => {
      button.disabled = !isActiveEvent;
    });
    competitionConfigModal.querySelectorAll('[data-open-event-schedule-modal]').forEach((button) => {
      button.disabled = persistedMatches <= 0;
      button.hidden = persistedMatches <= 0;
    });
    competitionConfigModal.querySelectorAll('[data-open-event-ranking-modal]').forEach((button) => {
      button.disabled = scheduledMatches <= 0 && persistedMatches <= 0;
    });
    return validSchedule;
  }
