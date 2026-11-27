/** Event planning projections and submission controller with explicit view and service dependencies. */
// @runtime-start
(function (root) {
  "use strict";

  function normalizeCompetitionStatus(status, normalizeStatusKey) {
    const normalized = normalizeStatusKey(status);
    if (["draft", ""].includes(normalized)) return "pending_configuration";
    if (["cancelled", "canceled", "anulado", "anulada"].includes(normalized))
      return "cancelled";
    if (["finalizada", "finalizado", "finalized"].includes(normalized))
      return "completed";
    if (["pendiente_evento"].includes(normalized)) return "pending_event";
    if (["pendiente_configuracion"].includes(normalized))
      return "pending_configuration";
    return String(status || "pending_configuration");
  }

  function competitionStatusLabel(status, normalizeStatusKey) {
    return (
      {
        pending_configuration: "Pendiente configuracion",
        pending_event: "Programado",
        active: "Activo",
        completed: "Finalizado",
        cancelled: "Anulado",
      }[normalizeCompetitionStatus(status, normalizeStatusKey)] ||
      status ||
      "Pendiente configuracion"
    );
  }

  function competitionMatchesFor(competitionId, allMatchList) {
    return allMatchList().filter(
      (match) =>
        String(match.competition_id || "") === String(competitionId || ""),
    );
  }

  function competitionMatchIsFinalized(match) {
    return ["FINALIZADA", "completed", "finalizada"].includes(
      String(match.estado || match.status || ""),
    );
  }

  function latestCompetitionPhase(matches) {
    const phaseOrder = {
      qualifier: 1,
      swiss: 2,
      groups: 3,
      league: 4,
      cup: 5,
      top_cut: 6,
      playoff: 7,
      final: 8,
    };
    return (
      matches
        .slice()
        .sort(
          (left, right) =>
            (phaseOrder[String(right.phase || "").toLowerCase()] || 0) -
              (phaseOrder[String(left.phase || "").toLowerCase()] || 0) ||
            Number(right.ronda || right.round_number || 0) -
              Number(left.ronda || left.round_number || 0),
        )[0]?.phase || ""
    );
  }

  function competitionPreviewLines(competition, allMatchList, divisionsByCode) {
    const matches = competitionMatchesFor(competition.id, allMatchList);
    const pending = matches.filter(
      (match) => !competitionMatchIsFinalized(match),
    ).length;
    const completed = matches.length - pending;
    const phase =
      latestCompetitionPhase(matches) ||
      competition.phase_structure ||
      competition.type ||
      "evento";
    const divisions = Object.values(divisionsByCode || {}).filter(
      (division) =>
        String(division.source_qualifier_id || "") ===
        String(competition.id || ""),
    );
    const standings = Array.isArray(competition.ranking)
      ? competition.ranking
      : [];
    const lines = [];
    if (!matches.length) {
      lines.push(
        "No hay combates programados todavia; primero genera calendario o guarda participantes.",
      );
    } else if (pending > 0) {
      lines.push(
        `Faltan ${pending} de ${matches.length} combates por cerrar en ${phase}; no conviene avanzar fase todavia.`,
      );
    } else {
      const normalizedPhase = String(phase).toLowerCase();
      const winners = matches.filter((match) =>
        ["A", "B"].includes(String(match.result || "").toUpperCase()),
      ).length;
      if (
        ["cup", "final", "single_elimination", "top_cut", "playoff"].includes(
          normalizedPhase,
        )
      ) {
        const nextMatches = Math.floor(winners / 2);
        lines.push(
          nextMatches > 0
            ? `Fase actual cerrada: puede crear exactamente ${nextMatches} combate(s) de la siguiente llave.`
            : "Fase actual cerrada: no hay suficientes ganadores para crear otra ronda.",
        );
      } else if (
        competition.type === "league" &&
        ["season_playoffs", "playoffs"].includes(
          String(competition.execution_model || ""),
        )
      ) {
        const nextMatches = Math.max(
          1,
          Math.floor(Math.min(standings.length || winners || completed, 8) / 2),
        );
        lines.push(
          `Liga cerrada: puede crear hasta ${nextMatches} combate(s) de playoff segun ranking.`,
        );
      } else if (competition.type === "league") {
        lines.push(
          "Liga de fase unica: al cerrar todos los combates se finaliza la tabla; no existe una fase siguiente.",
        );
      } else {
        const nextMatches = Math.max(
          1,
          Math.min(
            8,
            Math.ceil((standings.length || winners || completed) / 4),
          ),
        );
        lines.push(
          `Fase actual cerrada: puede crear ${nextMatches} combate(s) segun modelo y ranking.`,
        );
      }
    }
    if (["cup", "tournament"].includes(String(competition.type || ""))) {
      const deckCount = Number(
        competition.deck_pool_count ||
          (competition.selected_deck_ids || []).length ||
          0,
      );
      if (deckCount > 0 && deckCount % 2 !== 0) {
        lines.push(
          `Roster impar: hay ${deckCount} decks. Quita 1 deck desde Decks participantes para poder programar la copa.`,
        );
      }
    }
    if (competition.type === "qualifier") {
      lines.push(
        divisions.length
          ? `Divisiones conectadas: ${divisions.length}. Aplicar divisiones movera decks segun rangos guardados.`
          : "No hay divisiones conectadas a este clasificatorio; configura rangos antes de aplicar divisiones.",
      );
    }
    if (
      competition.type === "league" &&
      Number(competition.division_count || 0) > 1
    ) {
      const lock = String(competition.lock_division_moves || "0") === "1";
      const up = Number(competition.promotion_slots || 0);
      const down = Number(competition.relegation_slots || 0);
      lines.push(
        lock
          ? "Ascensos/descensos bloqueados para esta liga: la accion no movera decks."
          : "Configura los cupos por division en Ascenso y descenso. Requiere liga finalizada y ranking calculado.",
      );
    }
    return lines;
  }

  function completedQualifierOptions(
    data,
    selectedCompetitionDivisionId,
    normalizeCompetitionStatus,
  ) {
    const targetDivisionId = selectedCompetitionDivisionId();
    return (data.competitions || [])
      .filter((competition) => {
        if (String(competition.type || "") !== "qualifier") return false;
        if (normalizeCompetitionStatus(competition.status) !== "completed")
          return false;
        const ranking = Array.isArray(competition.ranking)
          ? competition.ranking
          : [];
        if (!ranking.length) return false;
        return (
          !targetDivisionId ||
          String(competition.division_id || "") === String(targetDivisionId)
        );
      })
      .sort(
        (a, b) =>
          String(b.ends_on || b.starts_on || b.created_at || "").localeCompare(
            String(a.ends_on || a.starts_on || a.created_at || ""),
          ) || Number(b.id || 0) - Number(a.id || 0),
      );
  }

  function qualifierLoadRange(qualifier, fromValue, toValue) {
    const ranking = Array.isArray(qualifier?.ranking) ? qualifier.ranking : [];
    const from = Math.max(
      1,
      Number.parseInt(String(fromValue || "1"), 10) || 1,
    );
    const to = Math.max(1, Number.parseInt(String(toValue || "1"), 10) || 1);
    const start = Math.min(from, to);
    const end = Math.max(from, to);
    const selectedRows = ranking.slice(start - 1, end);
    return { qualifier, ranking, from: start, to: end, selectedRows };
  }

  function submitCompetitionConfig(
    mode,
    {
      competitionConfigModal,
      data,
      activeCompetitionConfigId,
      competitionPersistedMatchCount,
      eventModelWarnings,
      bootstrap,
      document,
      selectedCompetitionDecks,
      participantOtherLeague,
      participantAddRestriction,
      compatibleParticipantRoster,
      syncCompetitionDeckInputs,
      updateCompetitionDeckConstraints,
      serverToday,
      competitionParticipantsPerSide,
      currentEligibleGroupCount,
      expectedQualifierMatchCount,
      competitionScheduledMatchCount,
      confirmScheduleQualifierModal,
      confirmScheduleQualifierModalNode,
      allMatchList,
      confirmStartQualifierModal,
      confirmStartQualifierModalNode,
      eventTypeLabel,
      alert,
    },
  ) {
    if (!competitionConfigModal) return;
    const model = (data.competitions || []).find(
      (c) => String(c.id) === String(activeCompetitionConfigId),
    );
    if (
      model &&
      ["start", "schedule_qualifier", "schedule_cup", "save_schedule"].includes(
        mode,
      ) &&
      competitionPersistedMatchCount(model.id) === 0
    ) {
      const warnings = eventModelWarnings(model);
      if (warnings.length) {
        alert(
          "Modelo pendiente de implementacion/configuracion: " +
            warnings.join(" "),
        );
        return;
      }
    }
    const invalidExchange = competitionConfigModal.querySelector(
      "[data-division-exchange]:invalid",
    );
    if (invalidExchange) {
      bootstrap.Modal.getOrCreateInstance(
        document.querySelector("[data-league-movement-modal]"),
      ).show();
      invalidExchange.reportValidity();
      return;
    }
    const occupiedDeck = selectedCompetitionDecks().find((deck) =>
      participantOtherLeague(deck),
    );
    if (occupiedDeck) {
      alert(participantAddRestriction(occupiedDeck));
      return;
    }
    if (!compatibleParticipantRoster(selectedCompetitionDecks())) {
      alert(
        "No se pueden guardar participantes de otro formato ni mezclar formatos. Retira los decks incompatibles.",
      );
      return;
    }
    const form = competitionConfigModal.querySelector("form");
    const modeInput = competitionConfigModal.querySelector(
      "[data-competition-config-mode]",
    );
    if (!form || !modeInput) return;
    const normalizedMode = mode === "status" ? "save" : mode;
    const type =
      competitionConfigModal.querySelector("[data-competition-config-type]")
        ?.value || "";
    const startInput = competitionConfigModal.querySelector(
      "[data-competition-config-start]",
    );
    modeInput.value = normalizedMode;
    syncCompetitionDeckInputs();
    updateCompetitionDeckConstraints();
    if (
      ["schedule_qualifier", "schedule_cup"].includes(normalizedMode) &&
      startInput &&
      startInput.value &&
      startInput.value < serverToday
    ) {
      startInput.reportValidity();
      alert("La fecha de inicio no puede ser menor a la fecha actual.");
      return;
    }
    if (
      normalizedMode === "start" &&
      startInput &&
      startInput.value &&
      startInput.value > serverToday
    ) {
      alert(`Este evento se podra comenzar el ${startInput.value}.`);
      return;
    }
    if (
      !["save", "save_schedule", "start"].includes(normalizedMode) &&
      !updateCompetitionDeckConstraints()
    ) {
      alert(
        type === "qualifier"
          ? "Configuracion invalida: el clasificatorio necesita minimo 4 decks y combinaciones coherentes entre match y duelos a clasificar."
          : "Configuracion invalida: agrega decks suficientes para organizar los combates del evento.",
      );
      return;
    }
    const participantsPerSide = competitionParticipantsPerSide(
      competitionConfigModal.querySelector("[data-competition-config-match]"),
    );
    const eligibleGroups = currentEligibleGroupCount(participantsPerSide);
    const expectedMatches = expectedQualifierMatchCount(
      eligibleGroups * participantsPerSide,
      participantsPerSide,
      Number(
        competitionConfigModal.querySelector(
          "[data-competition-config-qualifier-duels]",
        )?.value || 0,
      ),
    );
    if (
      normalizedMode === "start" &&
      type === "qualifier" &&
      competitionScheduledMatchCount() < expectedMatches
    ) {
      alert(
        "Primero debes programar todos los duelos y fechas tentativas del clasificatorio.",
      );
      return;
    }
    if (
      normalizedMode === "schedule_qualifier" &&
      type === "qualifier" &&
      confirmScheduleQualifierModal
    ) {
      const competitionId =
        competitionConfigModal.querySelector("[data-competition-config-id]")
          ?.value || "";
      confirmScheduleQualifierModalNode.dataset.pendingScheduleMode =
        "schedule_qualifier";
      const existingMatches = allMatchList().filter(
        (match) =>
          String(match.competition_id || "") === String(competitionId) &&
          !["FINALIZADA", "completed", "finalizada"].includes(
            String(match.estado || match.status || ""),
          ),
      );
      const message = confirmScheduleQualifierModalNode.querySelector(
        "[data-confirm-schedule-qualifier-message]",
      );
      const meta = confirmScheduleQualifierModalNode.querySelector(
        "[data-confirm-schedule-qualifier-meta]",
      );
      if (meta)
        meta.textContent = existingMatches.length
          ? `Este evento ya tiene ${existingMatches.length} duelos programados.`
          : "Este evento aun no tiene duelos programados.";
      if (message)
        message.textContent = existingMatches.length
          ? "Ya se genero un calendario de duelos y fechas para este evento. Si continuas, se reemplazara el calendario anterior no finalizado y se actualizara en base de datos."
          : "Si continuas, se generaran los cruces y fechas tentativas del clasificatorio y quedaran guardados en base de datos.";
      confirmScheduleQualifierModal.show();
      return;
    }
    if (
      normalizedMode === "schedule_cup" &&
      ["cup", "final"].includes(type) &&
      confirmScheduleQualifierModal
    ) {
      const competitionId =
        competitionConfigModal.querySelector("[data-competition-config-id]")
          ?.value || "";
      confirmScheduleQualifierModalNode.dataset.pendingScheduleMode =
        "schedule_cup";
      const existingMatches = allMatchList().filter(
        (match) =>
          String(match.competition_id || "") === String(competitionId) &&
          !["FINALIZADA", "completed", "finalizada"].includes(
            String(match.estado || match.status || ""),
          ),
      );
      if (existingMatches.length) {
        alert(
          "Esta copa ya fue programada. La llave existente no se puede volver a generar.",
        );
        return;
      }
      const message = confirmScheduleQualifierModalNode.querySelector(
        "[data-confirm-schedule-qualifier-message]",
      );
      const meta = confirmScheduleQualifierModalNode.querySelector(
        "[data-confirm-schedule-qualifier-meta]",
      );
      if (meta) meta.textContent = "Esta copa aun no tiene llave programada.";
      if (message)
        message.textContent =
          "Si continuas, se generara una sola vez la llave inicial de copa, sus cruces y fechas tentativas en base de datos.";
      confirmScheduleQualifierModal.show();
      return;
    }
    if (normalizedMode === "start" && confirmStartQualifierModal) {
      const scheduledMatches = competitionScheduledMatchCount();
      const title = confirmStartQualifierModalNode.querySelector(
        "[data-confirm-start-title]",
      );
      const subtitle = confirmStartQualifierModalNode.querySelector(
        "[data-confirm-start-subtitle]",
      );
      const message = confirmStartQualifierModalNode.querySelector(
        "[data-confirm-start-message]",
      );
      if (title)
        title.textContent = `Comenzar ${eventTypeLabel(type).toLowerCase()}`;
      if (subtitle)
        subtitle.textContent =
          "El evento cambiara a estado activo y su configuracion quedara bloqueada.";
      if (message)
        message.textContent =
          type === "league" &&
          startInput?.value > serverToday &&
          scheduledMatches > 0
            ? `La liga esta programada para el ${startInput.value}. Si confirmas, quedara activa desde ahora sin cambiar las fechas de sus combates. Esta seguro de continuar?`
            : ["cup", "final"].includes(type) && scheduledMatches === 0
              ? "Esta copa aun no ha sido programada. Al confirmar, el sistema generara automaticamente la llave y los combates antes de comenzar. Esta seguro de continuar?"
              : "El evento ya tiene su programacion preparada. Esta seguro de comenzar?";
      confirmStartQualifierModal.show();
      return;
    }
    form.requestSubmit();
  }

  const api = Object.freeze({
    normalizeCompetitionStatus,
    competitionStatusLabel,
    competitionMatchesFor,
    competitionMatchIsFinalized,
    latestCompetitionPhase,
    competitionPreviewLines,
    completedQualifierOptions,
    qualifierLoadRange,
    submitCompetitionConfig,
  });
  root.StationXEventPlanning = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
