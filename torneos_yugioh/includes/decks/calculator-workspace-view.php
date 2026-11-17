<section class="screen calculator-workspace" data-screen="calculadora_jugadas" hidden>
  <header><p class="eyebrow">Herramientas</p><h1>Calculadora de jugadas</h1><p>Elige Práctica para probar manos o Duelo para reproducir una partida contra otro deck.</p></header>
  <fieldset class="calculator-mode-picker"><legend>Modo de juego</legend><button class="btn btn-outline-info" type="button" data-calculator-mode="duel" aria-pressed="true">Duelo</button><button class="btn btn-outline-info" type="button" data-calculator-mode="practice" aria-pressed="false">Práctica</button></fieldset>
  <section class="panel calculator-deck-picker">
    <label>Buscar deck o equipo<input class="form-control" type="search" data-calculator-search placeholder="Nombre del deck o equipo"></label>
    <label>Formato<select class="form-select" data-calculator-format><option value="">Todos los formatos</option></select></label>
    <label>Tu deck<select class="form-select" data-calculator-deck><option value="">Selecciona tu deck</option></select></label>
    <p class="note" role="status" data-calculator-count></p>
  </section>
  <div data-calculator-workspace-board><p class="panel">Selecciona tu deck para preparar el tablero.</p></div>
</section>
