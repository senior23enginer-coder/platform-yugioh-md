    <section class="screen" data-screen="estadisticas" hidden>
        <?php if ($publicSeasonId > 0): ?><section class="public-season-banner"><i class="bi bi-eye"></i><div><small>Vista publica de temporada</small><strong><?php echo e($publicSeasonName); ?></strong><span>Pagina de consulta sin controles administrativos. Muestra resultados, ranking y metagame de esta temporada.</span></div><a class="btn btn-outline-info" href="./?export=entity_report&amp;entity_type=season&amp;entity_id=<?php echo (int) $publicSeasonId; ?>&amp;format=pdf"><i class="bi bi-file-earmark-pdf"></i> Informe PDF</a></section><?php endif; ?>
        <div data-stats-tables-panel hidden>
            <h2>Tablas</h2><p>Posiciones por evento y división, calculadas desde resultados reales.</p>
            <div class="control-strip"><select class="form-select" data-table-division><?php foreach ($divisions as $key => $division): ?><option value="<?php echo e($key); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></div>
            <section class="panel"><div class="table-responsive"><table class="table app-table standings"><thead><tr><th>Pos</th><th>Equipo / Deck</th><th>PTS</th><th>PJ</th><th>PG</th><th>PE</th><th>PP</th><th>CG</th><th>CP</th><th>DP</th><th>Zona</th></tr></thead><tbody data-standings-body></tbody></table></div><p class="note">Criterios: PTS, DP, CG, H2H. Si persiste empate extremo: combate unico.</p></section>
        </div>
    </section>

