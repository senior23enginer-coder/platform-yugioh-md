    <section class="screen" data-screen="buscar" hidden>
        <div class="page-title"><h1><?php echo e($uiText('route.buscar', 'title', 'Buscar')); ?></h1><p><?php echo e($uiText('route.buscar', 'subtitle', 'Encuentra decks, equipos, ligas, copas, torneos, rondas y pantallas del aplicativo.')); ?></p></div>
        <div class="search-layout">
            <aside class="panel search-filters">
                <div class="panel-head"><div><h2>Filtros</h2><p>Busqueda avanzada.</p></div><a href="#buscar">Limpiar</a></div>
                <label>Deck<select class="form-select"><option>Cualquier arquetipo</option><option>Combo</option><option>Control</option><option>Midrange</option></select></label>
                <label>Jugador<select class="form-select"><option>Todos los equipos</option><?php foreach (array_slice($teams, 0, 8) as $team): ?><option><?php echo e((string) $team['name']); ?></option><?php endforeach; ?></select></label>
                <label>Torneo<select class="form-select"><option>Todos los torneos</option><?php foreach (array_slice($competitions, 0, 8) as $competition): ?><option><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                <label>Categoria<select class="form-select"><option>Todas las categorias</option><?php foreach ($displayDivisions as $division): ?><option><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary w-100" type="button"><i class="bi bi-funnel"></i> Aplicar filtros</button>
            </aside>
            <section class="panel search-main">
                <div class="search-hero">
                    <div>
                        <h2>Que estas buscando?</h2>
                        <p>Busca decks, jugadores, torneos, copas y mas en YUGIOH MD.</p>
                        <div class="search-page-box">
                            <i class="bi bi-search"></i>
                            <input type="search" placeholder="Ej: Kewl Tune, Copa TCG Avanzado, OCG" data-search-page-input>
                        </div>
                    </div>
                </div>
                <div class="trend-strip">
                    <?php foreach (array_slice($allDecks, 0, 4) as $index => $deck): ?>
                        <article><span class="mini-card-art" style="--i:<?php echo $index; ?>"></span><b><?php echo e($deck['name']); ?></b><small><?php echo e($deck['division']); ?></small></article>
                    <?php endforeach; ?>
                </div>
                <div class="search-page-results mt-3" data-search-page-results></div>
            </section>
        </div>
    </section>

