/** Fixture rule selection and standings with explicit data and result ports. */
// @runtime-start
(function (root) {
  "use strict";
  function ruleForMatch(match, data) {
    if (match?.game_rule_id) {
      const directRule = (data.gameRules || []).find(
        (rule) =>
          String(rule.id || "") === String(match.game_rule_id || "") &&
          String(rule.active ?? "1") !== "0",
      );
      if (directRule) return directRule;
    }
    const competition = (data.competitions || []).find(
      (item) => String(item.id || "") === String(match?.competition_id || ""),
    );
    if (competition?.game_rule_id) {
      const competitionRule = (data.gameRules || []).find(
        (rule) =>
          String(rule.id || "") === String(competition.game_rule_id || "") &&
          String(rule.active ?? "1") !== "0",
      );
      if (competitionRule) return competitionRule;
    }
    const phase = String(match?.phase || "");
    const scopes =
      phase === "friendly"
        ? ["all_matches", "friendly", "match", "general"]
        : ["all_matches", "match", "general"];
    return (
      (data.gameRules || [])
        .filter(
          (rule) =>
            String(rule.active ?? "1") !== "0" &&
            scopes.includes(String(rule.scope || "")),
        )
        .sort((a, b) => {
          const scopeDiff =
            scopes.indexOf(String(a.scope || "")) -
            scopes.indexOf(String(b.scope || ""));
          if (scopeDiff !== 0) return scopeDiff;
          return (
            Number(a.priority_order || 100) - Number(b.priority_order || 100)
          );
        })[0] || null
    );
  }

  function computeStandings(
    division,
    data,
    allMatches,
    getSavedResult,
    evaluateDuel,
  ) {
    if (!data.divisions[division]) return [];
    const rows = data.divisions[division].decks.map((deck) => {
      const fullDeck =
        (data.allDecks || []).find((item) => item.name === deck[1]) || {};
      return {
        rank: deck[0],
        name: deck[1],
        team: fullDeck.team || deck.team || "Sin equipo",
        teamCode: fullDeck.team_code || fullDeck.team_symbol || "",
        teamPrimary: fullDeck.team_primary_color || "#36d8ff",
        teamSecondary: fullDeck.team_secondary_color || "#ffd25f",
        pts: 0,
        pj: 0,
        pg: 0,
        pe: 0,
        pp: 0,
        cg: 0,
        cp: 0,
        dp: 0,
        d5: 0,
        sweep: 0,
        streak: "-",
      };
    });
    const byName = Object.fromEntries(rows.map((row) => [row.name, row]));
    allMatches(division).forEach((match) => {
      const result = evaluateDuel(getSavedResult(match), match);
      if (result.state !== "FINALIZADA") return;
      const a = byName[match.a];
      const b = byName[match.b];
      if (!a || !b) return;
      const [cgA, cgB] = result.score.split("-").map(Number);
      a.pj += 1;
      b.pj += 1;
      a.cg += cgA;
      a.cp += cgB;
      b.cg += cgB;
      b.cp += cgA;
      if (getSavedResult(match)[4]) {
        if (result.winner === "A") a.d5 += 1;
        if (result.winner === "B") b.d5 += 1;
      }
      if (result.score === "2-0") a.sweep += 1;
      if (result.score === "0-2") b.sweep += 1;
      if (result.winner === "A") {
        a.pts += 3;
        a.pg += 1;
        b.pp += 1;
        a.streak = "W";
        b.streak = "L";
      }
      if (result.winner === "B") {
        b.pts += 3;
        b.pg += 1;
        a.pp += 1;
        b.streak = "W";
        a.streak = "L";
      }
      if (result.winner === "E") {
        a.pts += 1;
        b.pts += 1;
        a.pe += 1;
        b.pe += 1;
        a.streak = "D";
        b.streak = "D";
      }
    });
    rows.forEach((row) => {
      row.dp = row.cg - row.cp;
    });
    return rows.sort(
      (a, b) => b.pts - a.pts || b.dp - a.dp || b.cg - a.cg || a.rank - b.rank,
    );
  }

  const api = Object.freeze({ ruleForMatch, computeStandings });
  root.StationXFixturePolicy = api;
  if (typeof module === "object" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
