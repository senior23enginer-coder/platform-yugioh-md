<section class="screen deck-attributes-screen" data-screen="mundos" hidden>
    <header class="worlds-header"><div><span class="worlds-eyebrow">Decks y equipos · TCG</span><h1>Mundos</h1><p>Cada deck pertenece de uno a tres mundos según su naturaleza. El primero determina su clasificación principal.</p></div><button class="btn btn-primary btn-sm" type="button" data-attribute-new><i class="bi bi-plus-lg" aria-hidden="true"></i> Asignar deck</button></header>
    <p class="note worlds-status" role="status" data-attribute-status aria-live="polite">Cargando mundos…</p><div class="worlds-overview" data-world-overview></div>
    <section class="panel"><div class="worlds-filters"><label>Buscar mundo<input class="form-control" type="search" data-world-search placeholder="Oscuridad, Tierra, Luz…"></label><label>Integrantes<select class="form-select" data-world-state><option value="">Todos los mundos</option><option value="assigned">Con decks</option><option value="empty">Sin decks</option></select></label><button class="btn btn-outline-light btn-sm" type="button" data-world-clear>Limpiar</button></div>
        <div class="attribute-table-toolbar"><span data-world-count></span><button class="btn btn-outline-info btn-sm" type="button" data-attribute-refresh><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Actualizar</button></div>
        <div class="table-responsive"><table class="table app-table mobile-card-table worlds-table"><thead><tr><th>Mundo</th><th>Decks asociados</th><th>Como mundo principal</th><th>Acciones</th></tr></thead><tbody data-world-rows></tbody></table></div>
    </section>
</section>
<section class="screen deck-attributes-screen" data-screen="mundo_decks" hidden>
    <header class="worlds-header"><div><span class="worlds-eyebrow">Mundos · Solo TCG</span><h1 data-world-title>Decks del mundo</h1><p data-world-description></p></div><button class="btn btn-outline-light btn-sm" type="button" data-world-back><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mundos</button></header>
    <p class="worlds-status note" role="status" data-world-message aria-live="polite"></p>
    <section class="panel world-add-panel"><h2>Añadir deck a este mundo</h2><p>Selecciona cualquier deck TCG, aunque todavía no tenga cartas cargadas.</p><div class="world-add-controls"><label>Buscar deck o equipo<input class="form-control" type="search" data-world-candidate-search placeholder="Nombre del deck o equipo"></label><label>Deck TCG<select class="form-select" data-world-candidate><option value="">Selecciona un deck</option></select></label><button class="btn btn-primary btn-sm" type="button" data-world-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Añadir deck</button></div><p class="note" data-world-candidate-note></p></section>
    <section class="panel"><div class="worlds-member-toolbar"><label>Buscar entre los integrantes<input class="form-control" type="search" data-world-member-search placeholder="Deck o equipo"></label><label>Pertenencia<select class="form-select" data-world-membership><option value="">Todos los integrantes</option><option value="primary">Mundo principal</option><option value="secondary">Mundo secundario</option></select></label><button class="btn btn-outline-info btn-sm" type="button" data-attribute-refresh>Actualizar</button></div><p data-world-member-count aria-live="polite"></p>
        <div class="table-responsive"><table class="table app-table mobile-card-table worlds-members"><thead><tr><th>Deck</th><th>Equipo</th><th>Mundos</th><th>Acciones</th></tr></thead><tbody data-world-members></tbody></table></div>
    </section>
</section>
<section class="screen deck-attributes-screen" data-screen="mundos_deck_editar" hidden>
    <header class="worlds-header"><div><span class="worlds-eyebrow">Mundos · Solo TCG</span><h1 data-attribute-editor-title>Asignar mundos a un deck</h1><p>Elige hasta tres mundos y decide cuál será el principal.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-attribute-back>Volver</button></header>
    <div class="alert alert-info" role="status" data-attribute-editor-message aria-live="polite" hidden></div>
    <form class="panel attribute-editor" data-attribute-form>
        <label>Deck TCG<select class="form-select" data-attribute-deck required><option value="">Selecciona un deck</option></select></label><p class="note" data-attribute-editor-meta></p>
        <h2>Mundos disponibles</h2><p>Mínimo uno y máximo tres, sin repetir. Para cambiar el único mundo, añade el nuevo antes de quitar el anterior.</p><div class="attribute-choices" data-attribute-choices></div>
        <h2>Mundo principal</h2><p class="note">El primero determina dónde se clasifica el deck. Puedes promover cualquiera de los otros dos.</p>
        <p data-attribute-selection-count aria-live="polite">0 de 3 seleccionados</p><ol class="attribute-selected" data-attribute-selected></ol>
        <div class="attribute-form-actions"><button class="btn btn-primary" type="submit" data-attribute-save>Guardar mundos</button><button class="btn btn-outline-light" type="button" data-attribute-back>Cancelar</button></div>
    </form>
    <details class="panel attribute-history"><summary>Historial de cambios</summary><div data-attribute-history>Selecciona un deck para consultar su historial.</div></details>
</section>
