/** Calendar timing policy, independent from the DOM and shared browser state. */
// @runtime-start
(function (root) {
  "use strict";
  function settingEnabled(settings, key) {
    return ["1", "true", "yes", "on"].includes(
      String(settings[key] || "0").toLowerCase(),
    );
  }

  function matchTimingStatus(match, serverToday, settings) {
    const enabled = (key) => settingEnabled(settings, key);
    if (!match || match.estado === "FINALIZADA") {
      return {
        key: "done",
        label: "Finalizado",
        className: "ok",
        canCapture: true,
      };
    }
    if (
      match.competition_can_execute === false ||
      match.competition_can_execute === "0"
    ) {
      return {
        key: "competition_blocked",
        label:
          match.competition_blocked_reason ||
          "Evento bloqueado por prerequisitos",
        className: "blocked",
        canCapture: false,
      };
    }
    if (
      ["EN CURSO", "IN_PROGRESS", "RUNNING", "PAUSED"].includes(
        String(match.estado || match.status || "").toUpperCase(),
      )
    ) {
      const canCapture =
        Boolean(match.scheduled_on) &&
        (match.scheduled_on < serverToday
          ? enabled("allow_overdue_results")
          : match.scheduled_on > serverToday
            ? enabled("allow_early_results")
            : true);
      return {
        key: "in_progress",
        label: "En curso",
        className: "ok",
        canCapture,
      };
    }
    if (!match.scheduled_on) {
      return {
        key: "unscheduled",
        label: "Sin fecha",
        className: "warn",
        canCapture: false,
      };
    }
    if (match.scheduled_on < serverToday) {
      return {
        key: "overdue",
        label: "Vencido",
        className: "danger",
        canCapture: enabled("allow_overdue_results"),
      };
    }
    if (match.scheduled_on === serverToday) {
      return { key: "today", label: "Hoy", className: "ok", canCapture: true };
    }
    return {
      key: "future",
      label: enabled("allow_early_results") ? "Adelantable" : "Fecha futura",
      className: enabled("allow_early_results") ? "warn" : "blocked",
      canCapture: enabled("allow_early_results"),
    };
  }

  function isPlayableDate(dayIso, settings) {
    if (!dayIso) return false;
    const date = new Date(`${dayIso}T12:00:00`);
    if (Number.isNaN(date.getTime())) return false;
    const weekday = String(date.getDay());
    const validDays = String(settings.valid_weekdays || "1,2,3,4,5,6,0")
      .split(",")
      .map((value) => value.trim())
      .filter(Boolean);
    const specialDays = String(settings.special_days || "")
      .split(/[\s,]+/)
      .map((value) => value.trim())
      .filter(Boolean);
    return (
      validDays.includes(weekday) ||
      (settingEnabled(settings, "allow_special_days") &&
        specialDays.includes(dayIso))
    );
  }

  const api = Object.freeze({
    settingEnabled,
    matchTimingStatus,
    isPlayableDate,
  });
  root.StationXCalendarPolicy = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
