/**
 * manual-matches: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function manualCompetitionOption() {
    return manualCompetitionSelect ? manualCompetitionSelect.selectedOptions[0] : null;
  }
  function manualParticipantsPerSide() {
    const value = manualMatchFormatSelect?.value || manualParticipantsInput?.value || '1';
    const fromMatch = Number.parseInt(String(value), 10);
    return Math.max(1, Math.min(3, Number(manualParticipantsInput?.value || fromMatch || 1)));
  }
  function manualTargetDivisionId(competitionOption) {
    return String(competitionOption?.dataset?.divisionId || manualDivisionSelect?.value || '');
  }
  function manualEligibleTeamIds(targetDivisionId, participantsPerSide) {
    if (participantsPerSide <= 1) return null;
    const counts = {};
    manualDeckSelects[0]?.querySelectorAll('option[value]').forEach((option) => {
      if (!option.value || (targetDivisionId && option.dataset.divisionId !== targetDivisionId)) return;
      const teamId = option.dataset.teamId || '';
      if (!teamId) return;
      counts[teamId] = (counts[teamId] || 0) + 1;
    });
    return new Set(Object.keys(counts).filter((teamId) => counts[teamId] >= participantsPerSide));
  }
  function manualDeckAllowed(option, competitionOption) {
    if (!option || option.value === '') return true;
    const participantsPerSide = manualParticipantsPerSide();
    const targetDivisionId = manualTargetDivisionId(competitionOption);
    if (targetDivisionId && option.dataset.divisionId !== targetDivisionId) return false;
    if (!option.dataset.teamId) return false;
    const eligibleTeams = manualEligibleTeamIds(targetDivisionId, participantsPerSide);
    if (eligibleTeams && !eligibleTeams.has(option.dataset.teamId || '')) return false;
    if (!competitionOption || !competitionOption.value) return true;
    const pool = competitionOption.dataset.deckPool || 'all_active';
    if (pool === 'all_active' || pool === 'manual') return true;
    if (['division', 'qualified', 'top_cut'].includes(pool) && targetDivisionId) {
      return option.dataset.divisionId === targetDivisionId;
    }
    return true;
  }
  function applyManualCompetition() {
    const option = manualCompetitionOption();
    if (option && option.value) {
      if (manualPhaseSelect && option.dataset.type) manualPhaseSelect.value = option.dataset.type === 'special' ? 'friendly' : option.dataset.type;
      if (manualDivisionSelect && option.dataset.divisionId) manualDivisionSelect.value = option.dataset.divisionId;
      if (manualMatchFormatSelect && option.dataset.matchFormat) manualMatchFormatSelect.value = option.dataset.matchFormat;
      if (manualBestOfInput && option.dataset.bestOf) manualBestOfInput.value = option.dataset.bestOf;
      if (manualParticipantsInput && option.dataset.participants) manualParticipantsInput.value = option.dataset.participants;
    }
    let visibleDecks = 0;
    manualDeckSelects.forEach((select) => {
      Array.from(select.options).forEach((deckOption) => {
        const allowed = manualDeckAllowed(deckOption, option);
        deckOption.hidden = !allowed;
        deckOption.disabled = !allowed;
        if (allowed && deckOption.value) visibleDecks += 1;
      });
      const current = select.selectedOptions[0];
      if (current && current.disabled) {
        const next = Array.from(select.options).find((deckOption) => !deckOption.disabled);
        if (next) select.value = next.value;
      }
    });
    if (manualDeckPoolNote) {
      const pool = option && option.value ? (option.dataset.deckPool || 'all_active') : 'all_active';
      const ruleset = option && option.value ? (option.dataset.ruleset || 'custom') : 'general';
      manualDeckPoolNote.textContent = `Pool aplicado: ${pool} | Reglamento: ${ruleset} | Opciones visibles: ${visibleDecks}`;
    }
  }
  if (manualCompetitionSelect) manualCompetitionSelect.addEventListener('change', applyManualCompetition);
  if (manualDivisionSelect) manualDivisionSelect.addEventListener('change', applyManualCompetition);
  if (manualMatchFormatSelect) manualMatchFormatSelect.addEventListener('change', () => {
    if (manualParticipantsInput) manualParticipantsInput.value = String(Math.max(1, Math.min(3, Number.parseInt(manualMatchFormatSelect.value, 10) || 1)));
    applyManualCompetition();
  });
  if (manualParticipantsInput) manualParticipantsInput.addEventListener('input', applyManualCompetition);
  applyManualCompetition();

  const adminDivisionSelect = document.querySelector('[data-gestion-division-select]');
  const divisionDeckSearch = document.querySelector('[data-division-deck-search]');
  const divisionDeckPicker = document.querySelector('[data-division-deck-picker]');
  const divisionDeckAdd = document.querySelector('[data-division-deck-add]');
  const divisionSelectedDecks = document.querySelector('[data-division-selected-decks]');
  const divisionHiddenDecks = document.querySelector('[data-division-hidden-decks]');
  const divisionSelectedCount = document.querySelector('[data-division-selected-count]');
  let selectedDivisionDeckIds = new Set();
