    <section class="screen" data-screen="herramienta_lp" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.herramienta_lp', 'title', 'Calculadora LP')); ?></h1><p><?php echo e($uiText('route.herramienta_lp', 'subtitle', 'Herramienta de puntos de vida para duelos estilo YUGIOH Neuron.')); ?></p></div>
            <button class="btn btn-outline-light" type="button" data-lp-full-reset><i class="bi bi-arrow-counterclockwise"></i> Reset duelo</button>
        </div>
        <section class="lp-tool-shell" aria-label="Calculadora de puntos de vida">
            <div class="lp-config-rig">
                <div class="lp-config-presets" role="group" aria-label="Puntos iniciales">
                    <button type="button" data-lp-start-preset="8000">8000</button>
                    <button type="button" data-lp-start-preset="4000">4000</button>
                    <button type="button" data-lp-start-preset="16000">16000</button>
                </div>
                <label><span>LP inicial D1</span><input class="form-control" type="number" inputmode="numeric" min="0" step="100" data-lp-start="a" value="8000"></label>
                <label><span>LP inicial D2</span><input class="form-control" type="number" inputmode="numeric" min="0" step="100" data-lp-start="b" value="8000"></label>
                <button type="button" data-lp-apply-start><i class="bi bi-check2-circle"></i> Aplicar LP</button>
            </div>
            <div class="lp-timer-rig">
                <span>Timer</span>
                <strong data-lp-timer-display>00:00:00</strong>
                <div class="lp-timer-actions">
                    <button type="button" data-lp-timer-toggle><i class="bi bi-play-fill"></i> Iniciar</button>
                    <button type="button" data-lp-timer-reset><i class="bi bi-stop-fill"></i> Reset</button>
                </div>
            </div>
            <div class="lp-duel-board">
                <article class="lp-player lp-player-a" data-lp-player="a">
                    <div class="lp-player-head">
                        <label><span>Duelista 1</span><input class="form-control" data-lp-name="a" value="Duelista 1"></label>
                        <button type="button" data-lp-action="half" data-lp-target="a">1/2</button>
                    </div>
                    <div class="lp-display" data-lp-display="a">8000</div>
                    <div class="lp-bar"><i data-lp-bar="a"></i></div>
                    <div class="lp-controls" data-lp-controls="a">
                        <button type="button" data-lp-delta="-1000" data-lp-target="a">-1000</button>
                        <button type="button" data-lp-delta="-500" data-lp-target="a">-500</button>
                        <button type="button" data-lp-delta="-100" data-lp-target="a">-100</button>
                        <button type="button" data-lp-delta="-50" data-lp-target="a">-50</button>
                        <button type="button" data-lp-delta="50" data-lp-target="a">+50</button>
                        <button type="button" data-lp-delta="100" data-lp-target="a">+100</button>
                        <button type="button" data-lp-delta="500" data-lp-target="a">+500</button>
                        <button type="button" data-lp-delta="1000" data-lp-target="a">+1000</button>
                        <button type="button" data-lp-delta="2000" data-lp-target="a">+2000</button>
                    </div>
                    <div class="lp-custom">
                        <input class="form-control" type="number" inputmode="numeric" min="0" step="100" placeholder="Valor" data-lp-custom="a">
                        <button type="button" data-lp-custom-apply="subtract" data-lp-target="a">Restar</button>
                        <button type="button" data-lp-custom-apply="add" data-lp-target="a">Sumar</button>
                        <button type="button" data-lp-custom-apply="set" data-lp-target="a">Fijar</button>
                        <button type="button" data-lp-action="zero" data-lp-target="a">0 LP</button>
                    </div>
                </article>
                <div class="lp-center-column">
                    <button type="button" data-lp-action="undo"><i class="bi bi-arrow-counterclockwise"></i><span>Undo</span></button>
                    <button type="button" data-lp-action="reset"><i class="bi bi-arrow-clockwise"></i><span>8000</span></button>
                    <button type="button" data-lp-action="swap"><i class="bi bi-arrow-left-right"></i><span>Swap</span></button>
                    <button type="button" data-lp-action="turn"><i class="bi bi-arrow-repeat"></i><span>Turno <b data-lp-turn>1</b></span></button>
                    <button type="button" data-lp-action="coin"><i class="bi bi-circle-half"></i><span data-lp-coin>Moneda</span></button>
                    <button type="button" data-lp-action="dice"><i class="bi bi-dice-5"></i><span>Dado <b data-lp-dice>-</b></span></button>
                </div>
                <article class="lp-player lp-player-b" data-lp-player="b">
                    <div class="lp-player-head">
                        <label><span>Duelista 2</span><input class="form-control" data-lp-name="b" value="Duelista 2"></label>
                        <button type="button" data-lp-action="half" data-lp-target="b">1/2</button>
                    </div>
                    <div class="lp-display" data-lp-display="b">8000</div>
                    <div class="lp-bar"><i data-lp-bar="b"></i></div>
                    <div class="lp-controls" data-lp-controls="b">
                        <button type="button" data-lp-delta="-1000" data-lp-target="b">-1000</button>
                        <button type="button" data-lp-delta="-500" data-lp-target="b">-500</button>
                        <button type="button" data-lp-delta="-100" data-lp-target="b">-100</button>
                        <button type="button" data-lp-delta="-50" data-lp-target="b">-50</button>
                        <button type="button" data-lp-delta="50" data-lp-target="b">+50</button>
                        <button type="button" data-lp-delta="100" data-lp-target="b">+100</button>
                        <button type="button" data-lp-delta="500" data-lp-target="b">+500</button>
                        <button type="button" data-lp-delta="1000" data-lp-target="b">+1000</button>
                        <button type="button" data-lp-delta="2000" data-lp-target="b">+2000</button>
                    </div>
                    <div class="lp-custom">
                        <input class="form-control" type="number" inputmode="numeric" min="0" step="100" placeholder="Valor" data-lp-custom="b">
                        <button type="button" data-lp-custom-apply="subtract" data-lp-target="b">Restar</button>
                        <button type="button" data-lp-custom-apply="add" data-lp-target="b">Sumar</button>
                        <button type="button" data-lp-custom-apply="set" data-lp-target="b">Fijar</button>
                        <button type="button" data-lp-action="zero" data-lp-target="b">0 LP</button>
                    </div>
                </article>
            </div>
            <div class="lp-history-panel">
                <div class="panel-head compact-head"><div><h2>Historial</h2><p>Ultimos cambios de puntos de vida en esta mesa.</p></div><span class="status-chip" data-lp-status>Listo</span></div>
                <div class="lp-history-list" data-lp-history></div>
            </div>
        </section>
    </section>

    <?php require YGO_APP_ROOT.'/includes/news_view.php'; ?>

