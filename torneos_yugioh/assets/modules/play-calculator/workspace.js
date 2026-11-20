(function () {
  "use strict";
  const host = document.querySelector('[data-screen="calculadora_jugadas"]');
  if (!host) return;
  const data = window.DML_DATA || {},
    $ = (s) => host.querySelector(s),
    select = $("[data-calculator-deck]");
  const norm = (v) =>
    String(v ?? "")
      .normalize("NFD")
      .replace(/\p{Diacritic}/gu, "")
      .toLowerCase()
      .trim();
  const formats = {
    tcg_advanced: "TCG Advanced",
    tcg_traditional: "TCG Traditional",
    goat: "GOAT",
    rush_duel: "Rush Duel",
  };
  const decks = [...(data.allDecks || [])].sort((a, b) =>
    a.name.localeCompare(b.name, "es"),
  );
  const hasMain = (id) =>
    (data.cards || []).some(
      (c) =>
        String(c.deck_id) === String(id) &&
        String(c.section || "main").toLowerCase() === "main",
    );
  let chosen = "",mode='duel';
  const option = (value, label) => {
    const o = document.createElement("option");
    o.value = value;
    o.textContent = label;
    return o;
  };
  for (const code of [
    ...new Set(decks.flatMap((d) => d.format_codes || [])),
  ].sort())
    $("[data-calculator-format]").append(option(code, formats[code] || code));
  function draw() {
    const q = norm($("[data-calculator-search]").value),
      format = $("[data-calculator-format]").value;
    const rows = decks.filter(
      (d) =>
        (!format || (d.format_codes || []).includes(format)) &&
        q
          .split(/\s+/)
          .every((w) => norm(d.name + " " + (d.team || "")).includes(w)),
    );
    select.replaceChildren(option("", "Selecciona tu deck"));
    const selection = decks.find((d) => String(d.id) === chosen);
    const shown =
      selection && !rows.includes(selection) ? [selection, ...rows] : rows;
    for (const d of shown)
      select.append(
        option(
          String(d.id),
          `${d.name} · ${(d.format_codes || []).map((f) => formats[f] || f).join(" / ") || "sin formato"}${hasMain(d.id) ? "" : " · sin Main cargado"}`,
        ),
      );
    select.value = chosen;
    $("[data-calculator-count]").textContent =
      `${rows.length} de ${decks.length} decks coinciden. ${decks.filter((d) => hasMain(d.id)).length} tienen Main cargado. Los decks sin cartas también aparecen; necesitan su lista para generar manos.`;
  }
  select.addEventListener("change", () => {
    chosen = select.value;
    const deck = decks.find((d) => String(d.id) === chosen),
      board = $("[data-calculator-workspace-board]");
    if (!deck) {
      window.YgoPlayCalculator.unmount(board);
      board.textContent = "Selecciona tu deck para preparar el tablero.";
      return;
    }
    if (
      !(deck.format_codes || []).some((f) =>
        ["tcg_advanced", "goat", "rush_duel"].includes(f),
      )
    ) {
      window.YgoPlayCalculator.unmount(board);
      board.append(
        Object.assign(document.createElement("p"), {
          className: "note",
          textContent:
            "Este formato no dispone de tablero en el calculador actual. Selecciona TCG Advanced, GOAT o Rush Duel.",
        }),
      );
      return;
    }
    window.YgoPlayCalculator.mount(board, deck, data,{mode});
  });
  host.querySelectorAll('[data-calculator-mode]').forEach(button=>button.addEventListener('click',()=>{
    const next=button.dataset.calculatorMode,board=$('[data-calculator-workspace-board]');
    if(next===mode)return;
    if(chosen&&hasMain(chosen)&&!window.YgoPlayCalculator.setMode(board,next))return;
    mode=next;host.querySelectorAll('[data-calculator-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.calculatorMode===mode)));
    if(chosen&&!hasMain(chosen))select.dispatchEvent(new Event('change'));
  }));
  $("[data-calculator-search]").addEventListener("input", draw);
  $("[data-calculator-format]").addEventListener("change", draw);
  function restoreRequestedDeck() {
    const requested = sessionStorage.getItem('stationx:calculator-deck');
    if (!requested) return;
    sessionStorage.removeItem('stationx:calculator-deck');
    if (!decks.some(d => String(d.id) === requested)) return;
    chosen = requested;
    draw();
    select.dispatchEvent(new Event('change'));
  }
  // The dedicated calculator screen is reached from the deck profile.
  // Return to the profile without opening another modal or resetting the URL.
  const title = host.querySelector('.page-title') || host;
  if (!host.querySelector('[data-calculator-return-profile]')) {
    const back = document.createElement('a');
    back.href = '#deck_ficha';
    back.className = 'btn btn-outline-info calculator-return-profile';
    back.dataset.calculatorReturnProfile = '';
    back.innerHTML = '<i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a la ficha del deck';
    title.append(back);
  }
  window.addEventListener("dml:routechange", (e) => {
    if (e.detail.target !== "calculadora_jugadas") return;
    draw();
    restoreRequestedDeck();
  });
  draw();
  if (location.hash === '#calculadora_jugadas') restoreRequestedDeck();
})();
