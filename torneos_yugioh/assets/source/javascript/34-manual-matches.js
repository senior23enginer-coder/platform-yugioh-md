/**
 * manual-matches: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  const manualMatchController = window.StationXParticipantPolicy.createManualMatchController({
    manualCompetitionSelect, manualMatchFormatSelect, manualParticipantsInput,
    manualDivisionSelect, manualDeckSelects, manualPhaseSelect, manualBestOfInput,
    manualDeckPoolNote
  });
  const { manualCompetitionOption, manualParticipantsPerSide, manualTargetDivisionId,
    manualEligibleTeamIds, manualDeckAllowed, applyManualCompetition } = manualMatchController;
  manualMatchController.mount();

  const adminDivisionSelect = document.querySelector('[data-gestion-division-select]');
  const divisionDeckSearch = document.querySelector('[data-division-deck-search]');
  const divisionDeckPicker = document.querySelector('[data-division-deck-picker]');
  const divisionDeckAdd = document.querySelector('[data-division-deck-add]');
  const divisionSelectedDecks = document.querySelector('[data-division-selected-decks]');
  const divisionHiddenDecks = document.querySelector('[data-division-hidden-decks]');
  const divisionSelectedCount = document.querySelector('[data-division-selected-count]');
  let selectedDivisionDeckIds = new Set();
