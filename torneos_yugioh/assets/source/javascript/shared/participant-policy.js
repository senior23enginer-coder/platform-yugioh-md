/** Participant eligibility policies and manual match controller with injected DOM controls. */
// @runtime-start
(function (root) {
  "use strict";

  function competitionCanEditRoster(
    competition,
    currentCompetitionType,
    normalizeStatusKey,
  ) {
    const type = String(competition?.type || currentCompetitionType() || "");
    const status = normalizeStatusKey(competition?.status || "");
    if (
      [
        "completed",
        "finalizado",
        "finalizada",
        "cancelled",
        "canceled",
        "anulado",
        "anulada",
      ].includes(status)
    )
      return false;
    if (["cup", "tournament", "final"].includes(type)) return true;
    return !(
      competition?.configuration_locked === 1 ||
      competition?.configuration_locked === "1" ||
      ["active", "activo"].includes(status)
    );
  }

  function participantFormatCodes(deck) {
    return (deck?.format_codes || []).filter(
      (code) => code && code !== "custom",
    );
  }

  function compatibleParticipantRoster(decks, eventFormat) {
    let formats =
      eventFormat && eventFormat !== "custom" ? [eventFormat] : null;
    for (const deck of decks) {
      if (!deck?.id || !deck.team_id) return false;
      const own = participantFormatCodes(deck);
      formats =
        formats === null ? own : formats.filter((code) => own.includes(code));
      if (!formats.length) return false;
    }
    return true;
  }

  function participantOtherLeague(deck, currentCompetitionConfig, data) {
    const competition = currentCompetitionConfig();
    if (!competition || competition.type !== "league" || !deck) return null;
    return (
      (data.leagueDeckAssignments || []).find(
        (row) =>
          String(row.deck_id) === String(deck.id) &&
          String(row.season_id) === String(competition.season_id) &&
          String(row.competition_id) !== String(competition.id),
      ) || null
    );
  }

  function participantIsQualified(deck, currentCompetitionConfig) {
    const competition = currentCompetitionConfig();
    if (
      !competition ||
      (!competition.admission?.config &&
        competition.participant_source !== "PREVIOUS_EVENT" &&
        !["top_cut", "playoffs"].includes(competition.execution_model))
    )
      return true;
    return (
      !!competition.admission?.ok &&
      (competition.admission.selection?.ids || []).some(
        (id) => String(id) === String(deck?.id),
      )
    );
  }

  function participantAddRestriction(
    deck,
    participantIsQualified,
    participantOtherLeague,
  ) {
    if (!participantIsQualified(deck))
      return "Este deck no esta clasificado por las fuentes del evento. Configura Fuentes y clasificados en la ficha.";
    const other = participantOtherLeague(deck);
    return other
      ? `No se puede agregar: este deck pertenece a ${other.competition_name}. Un deck solo puede estar en una liga por temporada; puede participar en otros eventos.`
      : "No se puede agregar este deck: no se permite mezclar formatos.";
  }

  function eligibleTeamIdsForCompetition(
    divisionId,
    participantsPerSide,
    data,
    deckMatchesCompetitionFormat,
  ) {
    if (participantsPerSide <= 1) return null;
    const counts = {};
    (data.allDecks || []).forEach((deck) => {
      if (!deckMatchesCompetitionFormat(deck, divisionId)) return;
      const teamId = String(deck.team_id || "");
      if (!teamId) return;
      counts[teamId] = (counts[teamId] || 0) + 1;
    });
    return new Set(
      Object.keys(counts).filter(
        (teamId) => counts[teamId] >= participantsPerSide,
      ),
    );
  }

  function expectedQualifierMatchCount(
    deckCount,
    participantsPerSide,
    duelsPerDeck,
    competitionUsesWorkflow,
  ) {
    if (competitionUsesWorkflow()) return Math.floor(deckCount / 2);
    const groups =
      participantsPerSide > 0 ? Math.floor(deckCount / participantsPerSide) : 0;
    if (groups < 2 || duelsPerDeck < 2 || duelsPerDeck > groups - 1) return 0;
    const roundLimit = Math.min(
      Math.max(2, Math.min(5, duelsPerDeck)),
      groups - 1,
    );
    return Math.floor(groups / 2) * roundLimit;
  }

  function currentEligibleGroupCount(
    participantsPerSide,
    selectedCompetitionDivisionId,
    selectedCompetitionDecks,
    deckMatchesCompetitionFormat,
  ) {
    const divisionId = selectedCompetitionDivisionId();
    const validDecks = selectedCompetitionDecks().filter((deck) =>
      deckMatchesCompetitionFormat(deck, divisionId),
    );
    if (participantsPerSide <= 1) return validDecks.length;
    const selectedTeamCounts = validDecks.reduce((map, deck) => {
      const teamId = String(deck.team_id || "");
      if (!teamId) return map;
      map[teamId] = (map[teamId] || 0) + 1;
      return map;
    }, {});
    return Object.values(selectedTeamCounts).filter(
      (count) => count >= participantsPerSide,
    ).length;
  }

  function createManualMatchController({
    manualCompetitionSelect,
    manualMatchFormatSelect,
    manualParticipantsInput,
    manualDivisionSelect,
    manualDeckSelects,
    manualPhaseSelect,
    manualBestOfInput,
    manualDeckPoolNote,
  }) {
    function manualCompetitionOption() {
      return manualCompetitionSelect
        ? manualCompetitionSelect.selectedOptions[0]
        : null;
    }
    function manualParticipantsPerSide() {
      const value =
        manualMatchFormatSelect?.value || manualParticipantsInput?.value || "1";
      const fromMatch = Number.parseInt(String(value), 10);
      return Math.max(
        1,
        Math.min(3, Number(manualParticipantsInput?.value || fromMatch || 1)),
      );
    }
    function manualTargetDivisionId(competitionOption) {
      return String(
        competitionOption?.dataset?.divisionId ||
          manualDivisionSelect?.value ||
          "",
      );
    }
    function manualEligibleTeamIds(targetDivisionId, participantsPerSide) {
      if (participantsPerSide <= 1) return null;
      const counts = {};
      manualDeckSelects[0]
        ?.querySelectorAll("option[value]")
        .forEach((option) => {
          if (
            !option.value ||
            (targetDivisionId && option.dataset.divisionId !== targetDivisionId)
          )
            return;
          const teamId = option.dataset.teamId || "";
          if (!teamId) return;
          counts[teamId] = (counts[teamId] || 0) + 1;
        });
      return new Set(
        Object.keys(counts).filter(
          (teamId) => counts[teamId] >= participantsPerSide,
        ),
      );
    }
    function manualDeckAllowed(option, competitionOption) {
      if (!option || option.value === "") return true;
      const participantsPerSide = manualParticipantsPerSide();
      const targetDivisionId = manualTargetDivisionId(competitionOption);
      if (targetDivisionId && option.dataset.divisionId !== targetDivisionId)
        return false;
      if (!option.dataset.teamId) return false;
      const eligibleTeams = manualEligibleTeamIds(
        targetDivisionId,
        participantsPerSide,
      );
      if (eligibleTeams && !eligibleTeams.has(option.dataset.teamId || ""))
        return false;
      if (!competitionOption || !competitionOption.value) return true;
      const pool = competitionOption.dataset.deckPool || "all_active";
      if (pool === "all_active" || pool === "manual") return true;
      if (
        ["division", "qualified", "top_cut"].includes(pool) &&
        targetDivisionId
      ) {
        return option.dataset.divisionId === targetDivisionId;
      }
      return true;
    }
    function applyManualCompetition() {
      const option = manualCompetitionOption();
      if (option && option.value) {
        if (manualPhaseSelect && option.dataset.type)
          manualPhaseSelect.value =
            option.dataset.type === "special"
              ? "friendly"
              : option.dataset.type;
        if (manualDivisionSelect && option.dataset.divisionId)
          manualDivisionSelect.value = option.dataset.divisionId;
        if (manualMatchFormatSelect && option.dataset.matchFormat)
          manualMatchFormatSelect.value = option.dataset.matchFormat;
        if (manualBestOfInput && option.dataset.bestOf)
          manualBestOfInput.value = option.dataset.bestOf;
        if (manualParticipantsInput && option.dataset.participants)
          manualParticipantsInput.value = option.dataset.participants;
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
          const next = Array.from(select.options).find(
            (deckOption) => !deckOption.disabled,
          );
          if (next) select.value = next.value;
        }
      });
      if (manualDeckPoolNote) {
        const pool =
          option && option.value
            ? option.dataset.deckPool || "all_active"
            : "all_active";
        const ruleset =
          option && option.value
            ? option.dataset.ruleset || "custom"
            : "general";
        manualDeckPoolNote.textContent = `Pool aplicado: ${pool} | Reglamento: ${ruleset} | Opciones visibles: ${visibleDecks}`;
      }
    }

    function mount() {
      if (manualCompetitionSelect)
        manualCompetitionSelect.addEventListener(
          "change",
          applyManualCompetition,
        );
      if (manualDivisionSelect)
        manualDivisionSelect.addEventListener("change", applyManualCompetition);
      if (manualMatchFormatSelect)
        manualMatchFormatSelect.addEventListener("change", () => {
          if (manualParticipantsInput)
            manualParticipantsInput.value = String(
              Math.max(
                1,
                Math.min(
                  3,
                  Number.parseInt(manualMatchFormatSelect.value, 10) || 1,
                ),
              ),
            );
          applyManualCompetition();
        });
      if (manualParticipantsInput)
        manualParticipantsInput.addEventListener(
          "input",
          applyManualCompetition,
        );
      applyManualCompetition();
    }

    return Object.freeze({
      manualCompetitionOption,
      manualParticipantsPerSide,
      manualTargetDivisionId,
      manualEligibleTeamIds,
      manualDeckAllowed,
      applyManualCompetition,
      mount,
    });
  }

  const api = Object.freeze({
    competitionCanEditRoster,
    participantFormatCodes,
    compatibleParticipantRoster,
    participantOtherLeague,
    participantIsQualified,
    participantAddRestriction,
    eligibleTeamIdsForCompetition,
    expectedQualifierMatchCount,
    currentEligibleGroupCount,
    createManualMatchController,
  });
  root.StationXParticipantPolicy = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
