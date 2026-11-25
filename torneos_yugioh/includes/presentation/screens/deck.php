    <section class="screen" data-screen="deck" hidden>
        <div class="page-title deck-page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.deck', 'title', 'Decks')); ?></h1><p><?php echo e($uiText('route.deck', 'subtitle', 'Listado y filtros de decks guardados. La ficha se abre en una pantalla aparte.')); ?></p></div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-info" type="button" data-deck-download-all title="Descargar TXT de todos los decks y formatos"><i class="bi bi-download" aria-hidden="true"></i> Importar listado</button>
                <button class="btn btn-primary" type="button" data-open-deck-sheet=""><i class="bi bi-plus-circle"></i> Crear deck</button>
            </div>
        </div>
        <section class="panel">
            <div class="panel-head"><div><h2>Decks creados</h2><p>Consulta por tabla, filtra y abre la ficha de cada deck.</p></div><span class="status-chip"><?php echo count($allDecks); ?> activos</span></div>
            <p class="note" data-deck-download-status role="status" aria-live="polite"></p>
            <div class="deck-list-filters mb-3">
                <label class="deck-list-search-field">Buscar<input class="form-control" type="search" placeholder="Deck, equipo o arquetipo" data-deck-list-search></label>
                <label>Formato<select class="form-select" data-deck-list-format-filter><option value="">Todos</option><option value="tcg">TCG</option><option value="rush">RUSH DUEL</option><option value="goat">GOAT</option><option value="ocg">OCG</option></select></label>
                <label>Division<select class="form-select" data-deck-list-division-filter><option value="">Todas</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e(strtolower((string) ($division['short'] ?? $division['code'] ?? ''))); ?>"><?php echo e((string) ($division['short'] ?? $division['name'] ?? 'Division')); ?></option><?php endforeach; ?></select></label>
                <label>Equipo<select class="form-select" data-deck-list-team-filter><option value="">Todos</option><?php foreach ($teams as $team): ?><option value="<?php echo e(strtolower((string) ($team['name'] ?? ''))); ?>"><?php echo e((string) ($team['name'] ?? 'Equipo')); ?></option><?php endforeach; ?></select></label>
                <label>Tier<select class="form-select" data-deck-list-tier-filter><option value="">Todos</option><option value="tier zero">Tier Zero</option><option value="tier 1">Tier 1</option><option value="tier 2">Tier 2</option><option value="tier 3">Tier 3</option><option value="sin tier">Sin tier</option></select></label>
                <label>Actividad<select class="form-select" data-deck-list-activity-filter><option value="">Toda</option><option value="with_results">Con resultados</option><option value="without_results">Sin resultados</option></select></label>
                <label>Cartas cargadas<select class="form-select" data-deck-list-cards-filter><option value="">Todos los decks</option><option value="with_cards">Con cartas cargadas</option><option value="without_cards">Sin cartas cargadas</option></select></label>
                <label>Orden<select class="form-select" data-deck-list-order><option value="rank">Ranking</option><option value="name">Nombre A-Z</option><option value="matches">Mas partidas</option><option value="winrate">Mayor winrate</option></select></label>
                <button class="btn btn-outline-light" type="button" data-deck-list-clear><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button>
            </div>
            <div class="deck-list-filter-summary"><span data-deck-list-visible-count><?php echo count($allDecks); ?> de <?php echo count($allDecks); ?> decks</span></div>
            <div class="table-responsive">
                <table class="table app-table entity-table mobile-card-table">
                    <thead><tr><th>Deck</th><th>Equipo asociado</th><th>Formato</th><th>Liga actual</th><th>Divisi?n</th><th>Partidas</th><th>Partidas G/E/P</th><th>Posición en la liga</th><th>WR partidas</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($allDecks as $deck): ?>
                            <?php $deckEvents = (int) ($deckMatchCounts[(int) ($deck['id'] ?? 0)] ?? 0); ?>
                            <tr data-deck-list-row data-has-cards="<?php echo !empty($deck['has_cards']) ? '1' : '0'; ?>" data-deck-list-id="<?php echo e((string) ($deck['id'] ?? '')); ?>" data-team="<?php echo e(strtolower((string) ($deck['team'] ?? ''))); ?>" data-division="<?php echo e(strtolower((string) ($deck['division'] ?? ''))); ?>" data-search="<?php echo e(strtolower((string) (($deck['name'] ?? '') . ' ' . ($deck['team'] ?? '') . ' ' . ($deck['division'] ?? '') . ' ' . ($deck['display_archetype'] ?? $deck['archetype'] ?? '') . ' ' . ($deck['display_style'] ?? '')))); ?>">
                                <td data-label="Deck">
                                    <span class="deck-table-identity">
                                        <?php if (!empty($deck['cover_url'])): ?>
                                            <span class="deck-table-logo"><img loading="lazy" decoding="async" src="<?php echo e((string) $deck['cover_url']); ?>" alt="<?php echo e((string) $deck['name']); ?>"></span>
                                        <?php else: ?>
                                            <span class="deck-table-logo deck-table-logo-fallback"><?php echo e(strtoupper(substr((string) ($deck['name'] ?? '?'), 0, 1))); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($deck['has_cards'])): ?><button type="button" class="deck-loaded-marker" data-deck-loaded-filter aria-pressed="false" title="Cartas cargadas · filtrar decks con cartas" aria-label="<?php echo e($deck['name'].' tiene cartas cargadas. Filtrar decks con cartas.'); ?>"><i class="bi bi-stack" aria-hidden="true"></i></button><?php else: ?><span class="deck-loaded-marker is-empty" title="Sin cartas cargadas" aria-label="Sin cartas cargadas"><i class="bi bi-stack" aria-hidden="true"></i></span><?php endif; ?>
                                        <span class="deck-list-description"><strong><?php echo e((string) $deck['name']); ?></strong><small data-deck-list-style>Estilo: <?php echo e((string) (($deck['display_style'] ?? '') ?: 'Sin estilo registrado')); ?></small><small data-deck-list-archetype>Arquetipo: <?php echo e((string) ($deck['display_archetype'] ?? $deck['archetype'] ?? $deck['name'])); ?></small><span class="attribute-tags" data-attribute-tags-deck="<?php echo (int)$deck['id']; ?>"></span></span>
                                    </span>
                                </td>
                                <td data-label="Equipo"><?php echo e((string) (($deck['team'] ?? '') ?: 'Sin equipo')); ?></td>
                                <td data-label="Formato" data-deck-list-format>Sin registros</td>
                                <td data-label="Liga actual" data-deck-list-league>Sin liga actual</td>
                                <td data-label="Divisi?n" data-deck-list-division>Sin divisi?n</td>
                                <td data-label="Partidas" data-deck-list-matches>0</td>
                                <td data-label="Partidas G/E/P" data-deck-list-match-record>0 / 0 / 0</td>
                                <td data-label="Posición en la liga" data-deck-list-league-position>Sin clasificación</td>
                                <td data-label="WR partidas" data-deck-list-winrate>0%</td>
                                <td data-label="Acciones"><div class="d-flex gap-2"><button class="btn btn-outline-info btn-sm" type="button" data-open-deck-sheet="<?php echo e((string) ($deck['id'] ?? '')); ?>" title="Ver ficha" aria-label="Ver ficha de <?php echo e((string) $deck['name']); ?>"><i class="bi bi-eye" aria-hidden="true"></i></button><button class="btn btn-outline-info btn-sm" type="button" data-deck-download="<?php echo e((string) ($deck['id'] ?? '')); ?>" title="Descargar listado TXT"><i class="bi bi-download" aria-hidden="true"></i> Importar</button></div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

<div class="deck-template-overlay" data-deck-template-modal hidden>
  <section class="deck-template-dialog" role="dialog" aria-modal="true" aria-labelledby="deck-template-title">
    <div class="deck-template-head">
      <div><h2 id="deck-template-title">Crear deck desde plantilla TXT</h2><p class="deck-template-help">Descarga la plantilla, edítala y súbela para crear el deck con sus cartas Main, Extra, Side y Reserva.</p></div>
      <button class="btn btn-outline-light" type="button" data-deck-template-close aria-label="Cerrar">×</button>
    </div>
    <div class="deck-template-actions">
      <article class="deck-template-action"><i class="bi bi-file-earmark-arrow-down fs-3" aria-hidden="true"></i><h3>Descargar plantilla</h3><p>TXT editable con nombre, arquetipo, estilo, notas y secciones de cartas.</p><button class="btn btn-outline-info" type="button" data-deck-template-download>Descargar TXT</button></article>
      <article class="deck-template-action"><i class="bi bi-upload fs-3" aria-hidden="true"></i><h3>Subir TXT</h3><p>Importa una plantilla editada para crear un nuevo deck en la base de datos.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="import_deck_template"><input class="form-control mb-2" name="deck_template_txt" data-deck-template-file type="file" accept=".txt,text/plain" required><button class="btn btn-primary" type="submit">Crear deck desde TXT</button></form></article>
      <article class="deck-template-action"><i class="bi bi-arrow-repeat fs-3" aria-hidden="true"></i><h3>Sincronizar</h3><p>Recarga el listado de decks y cartas guardados en el servidor.</p><button class="btn btn-outline-info" type="button" data-deck-template-sync>Sincronizar listado</button></article>
    </div>
    <p class="note" data-deck-template-status role="status" aria-live="polite"></p>
    <p class="deck-template-help">La importación rechaza duplicados de nombre y datos no válidos; no reemplaza decks existentes.</p>
  </section>
</div>
