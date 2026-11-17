<section class="screen card-inspector-screen" data-screen="inspector_cartas" hidden>
  <header><p class="eyebrow">Herramientas</p><h1>Inspector de cartas</h1><p>Busca una carta, lee su información y descubre qué decks de la plataforma la utilizan.</p></header>
  <form class="panel ci-search" data-ci-form>
    <label>Buscar en<select class="form-select" data-ci-source><option value="local">Cartas locales · decks de la plataforma</option><option value="api">API de cartas · YGOPRODeck</option></select></label>
    <label>Carta<input class="form-control" type="search" maxlength="100" data-ci-query placeholder="Nombre o identificador de carta"></label>
    <button class="btn btn-primary" type="submit">Buscar</button>
    <p class="note" data-ci-help>La búsqueda local incluye Main, Extra, Side y Reserva de todos los decks registrados.</p>
  </form>
  <p role="status" aria-live="polite" data-ci-status></p>
  <div class="ci-layout"><section class="panel"><h2>Resultados</h2><div class="ci-results" data-ci-results></div><div class="ci-pagination"><button class="btn btn-outline-info" type="button" data-ci-prev>Anterior</button><span data-ci-page></span><button class="btn btn-outline-info" type="button" data-ci-next>Siguiente</button></div></section>
    <section class="panel ci-detail" data-ci-detail aria-label="Información de la carta"><h2>Selecciona una carta</h2><p>Abre un resultado para leerlo y consultar sus decks.</p></section></div>
</section>
