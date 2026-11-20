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
  const formatLabels = {
    tcg_advanced: "TCG Advanced",
    tcg_traditional: "TCG Traditional",
    ocg: "OCG",
    ocg_advanced: "OCG Advanced",
  };
  const playableFormats = Object.keys(formatLabels);
  const isTcgOrOcg = deck => (deck.format_codes || []).some(code => playableFormats.includes(String(code).toLowerCase()));
  const decks = [...(data.allDecks || [])]
    .filter(isTcgOrOcg)
    .sort((a,b) => a.name.localeCompare(b.name,"es"));
  const hasMain = id => (data.cards || []).some(card =>
    String(card.deck_id) === String(id) &&
    String(card.section || "main").toLowerCase() === "main" &&
    Number(card.quantity ?? 1) > 0
  );
  let chosen = "", mode = 'duel';
  const option = (value,label) => {
    const o = document.createElement('option');
    o.value = value;
    o.textContent = label;
    return o;
  };
  // Preserve the existing select as the calculator's data interface, but
  // replace its visible controls with one keyboard-accessible searchable picker.
  const oldSearch = $('[data-calculator-search]');
  const oldFormat = $('[data-calculator-format]');
  if (oldSearch) oldSearch.closest('label').hidden = true;
  if (oldFormat) oldFormat.closest('label').hidden = true;
  select.closest('label').hidden = true;
  const pickerControl = document.createElement('div');
  pickerControl.className = 'calculator-combobox';
  pickerControl.innerHTML = `<label for="calculator-searchable-deck">Buscar y seleccionar deck TCG u OCG</label>
    <div class="calculator-combobox-input">
      <input id="calculator-searchable-deck" type="text" autocomplete="off" role="combobox"
        aria-haspopup="listbox" aria-expanded="false" aria-controls="calculator-deck-options"
        placeholder="Escribe el nombre o selecciona un deck…" data-calculator-combobox>
      <button type="button" aria-label="Mostrar decks" data-calculator-combobox-toggle>▾</button>
    </div>
    <div id="calculator-deck-options" class="calculator-combobox-list" role="listbox"
      aria-label="Decks TCG y OCG" data-calculator-options hidden></div>`;
  select.closest('.calculator-deck-picker').insertBefore(pickerControl,select.closest('label'));
  const input = pickerControl.querySelector('[data-calculator-combobox]');
  const menu = pickerControl.querySelector('[data-calculator-options]');
  const toggle = pickerControl.querySelector('[data-calculator-combobox-toggle]');
  let highlighted = -1;
  let menuRows = [];
  const formatName = d => (d.format_codes || []).filter(f => playableFormats.includes(f)).map(f=>formatLabels[f] || f).join(' / ');
  function closeOptions() {
    menu.hidden = true;
    input.setAttribute('aria-expanded','false');
    input.removeAttribute('aria-activedescendant');
    highlighted = -1;
  }
  function focusOption(index) {
    if (!menuRows.length) return;
    highlighted = (index + menuRows.length) % menuRows.length;
    menuRows.forEach((row,i) => {
      row.classList.toggle('is-highlighted',i===highlighted);
      row.setAttribute('aria-selected',String(i===highlighted));
    });
    input.setAttribute('aria-activedescendant',menuRows[highlighted].id);
    menuRows[highlighted].scrollIntoView({block:'nearest'});
  }
  function renderOptions(show = false) {
    const query = norm(input.value);
    const filtered = decks.filter(d => !query || norm(d.name).includes(query));
    menu.replaceChildren();
    menuRows = [];
    for (const d of filtered) {
      const enabled = hasMain(d.id);
      const row = document.createElement('button');
      row.type = 'button';
      row.id = 'calculator-deck-option-'+Number(d.id);
      row.className = 'calculator-combobox-option'+(enabled?'':' is-unavailable');
      row.setAttribute('role','option');
      row.setAttribute('aria-disabled',String(!enabled));
      row.disabled = !enabled;
      row.dataset.deckId = String(d.id);
      const title = document.createElement('span');
      title.textContent = d.name;
      const detail = document.createElement('small');
      detail.textContent = formatName(d)+(enabled?'':' · Sin Main Deck cargado');
      row.append(title,detail);
      if (enabled) row.addEventListener('click',()=>chooseDeck(d.id));
      menu.append(row);
      if (enabled) menuRows.push(row);
    }
    if (!filtered.length) {
      const empty = document.createElement('p');
      empty.className = 'calculator-combobox-empty';
      empty.textContent = 'No hay decks TCG u OCG con ese nombre.';
      menu.append(empty);
    }
    if (show) {
      menu.hidden = false;
      input.setAttribute('aria-expanded','true');
    }
    highlighted = -1;
    input.removeAttribute('aria-activedescendant');
    $('[data-calculator-count]').textContent =
      filtered.length+' de '+decks.length+' decks TCG/OCG. '+decks.filter(d=>hasMain(d.id)).length+
      ' con Main cargado; los demás no se pueden seleccionar.';
  }
  function chooseDeck(id) {
    const d = decks.find(item => String(item.id) === String(id));
    if (!d || !hasMain(d.id)) return;
    chosen = String(d.id);
    input.value = d.name;
    select.replaceChildren(option('', 'Selecciona tu deck'),option(chosen,d.name));
    select.value = chosen;
    closeOptions();
    select.dispatchEvent(new Event('change'));
  }
  function draw() {
    renderOptions(false);
    if (chosen) {
      const chosenDeck = decks.find(d => String(d.id) === chosen);
      if (chosenDeck) input.value = chosenDeck.name;
    }
  }
  input.addEventListener('focus',()=>renderOptions(true));
  input.addEventListener('input',()=>{
    chosen = '';
    select.value = '';
    select.dispatchEvent(new Event('change'));
    renderOptions(true);
  });
  toggle.addEventListener('click',()=>{
    if (menu.hidden) {renderOptions(true);input.focus();}
    else {closeOptions();input.focus();}
  });
  input.addEventListener('keydown',event=>{
    if (event.key === 'Escape') {closeOptions();return;}
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (menu.hidden) renderOptions(true);
      focusOption(highlighted+(event.key==='ArrowDown'?1:-1));
    }
    if (event.key === 'Enter' && !menu.hidden && highlighted>=0) {
      event.preventDefault();
      menuRows[highlighted].click();
    }
  });
  document.addEventListener('pointerdown',event=>{
    if (!pickerControl.contains(event.target)) closeOptions();
  });
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
      !isTcgOrOcg(deck)
    ) {
      window.YgoPlayCalculator.unmount(board);
      board.append(
        Object.assign(document.createElement("p"), {
          className: "note",
          textContent:
            "Este formato no dispone de tablero en el calculador actual. Selecciona un deck TCG u OCG con Main Deck cargado.",
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
    if (scroll) host.scrollIntoView({block:'start',behavior:'auto'});
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
    chooseDeck(requested);
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
    if (e.detail.target !== "calculadora_jugadas") {
      // A calculator-only URL parameter must not leak into other Station X screens.
      const url = new URL(location.href);
      if (url.searchParams.has('calculator_stage')) {
        url.searchParams.delete('calculator_stage');
        history.replaceState(history.state,'',url.pathname+url.search+url.hash);
      }
      return;
    }
    draw();
    restoreRequestedDeck();
    if (new URL(location.href).searchParams.get('calculator_stage')!=='board') stage('setup',false);
  });
  draw();
  if (location.hash === '#calculadora_jugadas') restoreRequestedDeck();
})();
