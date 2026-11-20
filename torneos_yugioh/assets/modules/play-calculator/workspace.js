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
  // Two-step workflow: selection first, board only after explicit confirmation.
  // Keep the real calculator mounted but hidden during setup so the duel state
  // survives returning to the configuration screen.
  const board = $('[data-calculator-workspace-board]');
  const picker = select.closest('.calculator-deck-picker') || select.parentElement;
  const setup = document.createElement('section');
  setup.className = 'calculator-duel-setup';
  setup.dataset.calculatorDuelSetup = '';
  setup.innerHTML = `<div class="calculator-duel-setup-head"><span class="learn-kicker">Paso 1 de 2 · Preparar duelo</span><h2>Elige tu oponente</h2><p>Selecciona un rival del mismo formato. El tablero se abrirá en la siguiente vista, sin tener que desplazarte por la configuración.</p></div><label>Deck oponente<select class="form-select" data-calculator-setup-opponent><option value="">Selecciona un oponente</option></select></label><p data-calculator-setup-status role="status" aria-live="polite"></p><button class="btn btn-info" type="button" data-calculator-continue disabled>Continuar al tablero <i class="bi bi-arrow-right" aria-hidden="true"></i></button>`;
  (picker || board).insertAdjacentElement('afterend',setup);
  const opponentSelect = setup.querySelector('[data-calculator-setup-opponent]');
  const continueButton = setup.querySelector('[data-calculator-continue]');
  const setupStatus = setup.querySelector('[data-calculator-setup-status]');
  const boardToolbar = document.createElement('div');
  boardToolbar.className = 'calculator-duel-toolbar';
  boardToolbar.dataset.calculatorDuelToolbar = '';
  boardToolbar.innerHTML = '<button class="btn btn-outline-info" type="button" data-calculator-back-setup><i class="bi bi-arrow-left" aria-hidden="true"></i> Cambiar oponente</button><strong data-calculator-duel-label></strong>';
  board?.parentElement?.insertBefore(boardToolbar,board);
  let activeStage = 'setup';
  function refreshOpponents() {
    const own = decks.find(d => String(d.id) === chosen);
    const previous = opponentSelect.value;
    opponentSelect.replaceChildren(option('','Selecciona un oponente'));
    if (own) {
      const supported = ['tcg_advanced','goat','rush_duel'];
      const compatible = decks.filter(d => (d.format_codes||[]).some(f => supported.includes(f) && (own.format_codes||[]).includes(f)));
      for (const d of compatible) {
        opponentSelect.append(option(String(d.id),d.name+(hasMain(d.id)?'':' · Main no cargado')));
      }
      opponentSelect.append(option('free','Práctica libre (sin rival)'));
    }
    opponentSelect.value = [...opponentSelect.options].some(o=>o.value===previous)?previous:'';
    const valid = !!own && hasMain(own.id) && !!opponentSelect.value &&
      (own.format_codes||[]).some(f=>['tcg_advanced','goat','rush_duel'].includes(f));
    continueButton.disabled = !valid;
    setupStatus.textContent = !own?'Selecciona primero tu deck.':!hasMain(own.id)?'Tu deck necesita cartas en Main para preparar el tablero.':!opponentSelect.value?'Selecciona un rival o práctica libre.':'Listo para entrar al tablero.';
  }
  function stage(next, scroll = true) {
    activeStage = next;
    host.classList.toggle('calculator-stage-board',next==='board');
    host.classList.toggle('calculator-stage-setup',next==='setup');
    setup.hidden = next!=='setup';
    boardToolbar.hidden = next!=='board';
    if (board) board.hidden = next!=='board';
    if (next==='setup') refreshOpponents();
    if (scroll) host.scrollIntoView({block:'start',behavior:'instant'});
  }
  function applyOpponent() {
    const target = opponentSelect.value;
    if (!target || !chosen) return false;
    if (target==='free') {
      if (!window.YgoPlayCalculator.setMode(board,'practice')) return false;
      mode='practice';
    } else {
      if (mode==='practice' && !window.YgoPlayCalculator.setMode(board,'duel')) return false;
      mode='duel';
      const selectRival = board?.querySelector('[data-play-opponent]');
      if (!selectRival || ![...selectRival.options].some(o=>o.value===target&&!o.disabled)) {
        setupStatus.textContent='El rival seleccionado no está disponible para este deck.';
        return false;
      }
      if (selectRival.value!==target) {
        selectRival.value=target;
        selectRival.dispatchEvent(new Event('change',{bubbles:true}));
      }
    }
    const rival=target==='free'?'Práctica libre':decks.find(d=>String(d.id)===target)?.name||'Rival';
    boardToolbar.querySelector('[data-calculator-duel-label]').textContent=(decks.find(d=>String(d.id)===chosen)?.name||'Tu deck')+' vs '+rival;
    return true;
  }
  opponentSelect.addEventListener('change',refreshOpponents);
  select.addEventListener('change',()=>{
    if (activeStage==='board') {
      const url=new URL(location.href);
      url.searchParams.delete('calculator_stage');
      history.replaceState(history.state,'',url.pathname+url.search+url.hash);
      stage('setup',false);
    }
    refreshOpponents();
  });
  continueButton.addEventListener('click',()=>{
    if (continueButton.disabled || !applyOpponent()) return;
    const url=new URL(location.href);
    url.searchParams.set('calculator_stage','board');
    history.pushState({...history.state,stationxCalculatorBoard:true},'',url.pathname+url.search+url.hash);
    stage('board');
  });
  boardToolbar.querySelector('[data-calculator-back-setup]').addEventListener('click',()=>{
    if (history.state?.stationxCalculatorBoard && new URL(location.href).searchParams.get('calculator_stage')==='board') {
      history.back();
    } else {
      const url=new URL(location.href);
      url.searchParams.delete('calculator_stage');
      history.replaceState(history.state,'',url.pathname+url.search+url.hash);
      stage('setup');
    }
  });
  window.addEventListener('popstate',()=>{
    if (location.hash!=='#calculadora_jugadas') return;
    stage(new URL(location.href).searchParams.get('calculator_stage')==='board'?'board':'setup');
  });
  stage('setup',false);
  function restoreRequestedDeck() {
    const requested = sessionStorage.getItem('stationx:calculator-deck');
    if (!requested) return;
    sessionStorage.removeItem('stationx:calculator-deck');
    if (!decks.some(d => String(d.id) === requested)) return;
    chosen = requested;
    draw();
    select.dispatchEvent(new Event('change'));
    refreshOpponents();
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
    if (new URL(location.href).searchParams.get('calculator_stage')!=='board') stage('setup',false);
  });
  draw();
  if (location.hash === '#calculadora_jugadas') restoreRequestedDeck();
})();
