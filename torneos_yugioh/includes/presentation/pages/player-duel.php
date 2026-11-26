<?php
?><!doctype html>
    <html lang="es" data-duel-player-side="<?php echo e($duelPlayerSide); ?>" data-duel-player-match="<?php echo $duelPlayerMatchId; ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
        <meta name="theme-color" content="#06111f">
        <title><?php echo e($ownTeam); ?> | Pantalla de duelo</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="./assets/player-duel.css?v=20260927-surrender-calc-1" rel="stylesheet">
    </head>
    <body>
    <main class="player-duel-shell" data-player-duel-root>
        <header class="player-duel-header">
            <div class="player-duel-brand"><?php if ($ownLogo !== ''): ?><img src="<?php echo e($ownLogo); ?>" alt="Logo de <?php echo e($ownTeam); ?>"><?php else: ?><span><?php echo e($duelPlayerSide); ?></span><?php endif; ?></div>
            <div><small>Pantalla del lado <?php echo e($duelPlayerSide); ?></small><h1><?php echo e($ownTeam); ?></h1><p><?php echo e($ownDeck); ?></p></div>
            <span class="player-sync is-waiting" data-player-sync><i></i> Conectando</span>
        </header>
        <?php if (!$duelPlayerMatch): ?>
            <section class="player-message"><h2>Partida no encontrada</h2><p>Revisa el enlace proporcionado por el juez.</p></section>
        <?php else: ?>
            <section class="player-match-meta"><span><?php echo e((string) ($duelPlayerMatch['competition_name'] ?? 'Partida')); ?></span><b>R<?php echo (int) ($duelPlayerMatch['round_number'] ?? 0); ?> · Mesa <?php echo (int) ($duelPlayerMatch['table_number'] ?? 0); ?></b><strong data-player-duel-number>D1</strong></section>
            <section class="player-turn-panel" data-player-turn-panel>
                <small>Turno actual</small><h2 data-player-turn-label>Esperando al juez</h2><p data-player-turn-detail>El duelo todavía no ha comenzado.</p>
                <button type="button" class="player-primary" data-player-end-turn disabled><i class="bi bi-check2-circle"></i><span>Terminar mi turno</span></button>
                <button type="button" class="player-surrender" data-player-surrender disabled><i class="bi bi-flag-fill"></i><span>Rendirme</span></button>
            </section>
            <section class="player-scoreboard">
                <article class="is-own"><small>Mis LP</small><strong data-player-lp-own>8000</strong><span><?php echo e($ownTeam); ?></span></article>
                <b>VS</b>
                <article><small>LP rival</small><strong data-player-lp-rival>8000</strong><span><?php echo e((string) ($duelPlayerMatch['team_' . strtolower($opponentSide)] ?? ('Equipo ' . $opponentSide))); ?></span></article>
            </section>
            <section class="player-tools">
                <header><div><small>Herramienta personal</small><h2>Calculadora de LP</h2></div><strong data-player-entry>0</strong></header>
                <div class="player-keypad"><?php foreach (['7','8','9','4','5','6','1','2','3','0','00','000'] as $key): ?><button type="button" data-player-key="<?php echo e($key); ?>"><?php echo e($key); ?></button><?php endforeach; ?></div>
                <div class="player-operations">
                    <button type="button" data-player-lp-op="clear"><i class="bi bi-backspace"></i><span>Borrar</span></button>
                    <button type="button" data-player-lp-op="subtract"><i class="bi bi-dash-lg"></i><span>Restar</span></button>
                    <button type="button" data-player-lp-op="add"><i class="bi bi-plus-lg"></i><span>Sumar</span></button>
                    <button type="button" data-player-lp-op="half"><b>1/2</b><span>Mitad</span></button>
                    <button type="button" data-player-lp-op="reset"><i class="bi bi-arrow-repeat"></i><span>Inicial</span></button>
                </div>
            </section>
            <section class="player-tools player-standard-tool">
                <header><div><small>Calculo auxiliar</small><h2>Calculadora estandar</h2></div><strong data-player-standard-display>0</strong></header>
                <div class="player-standard-keypad">
                    <?php foreach (['7','8','9','divide','4','5','6','multiply','1','2','3','subtract','clear','0','equals','add'] as $key): ?>
                        <?php $label = ['divide' => '&divide;', 'multiply' => '&times;', 'subtract' => '&minus;', 'add' => '+', 'clear' => 'C', 'equals' => '='][$key] ?? $key; ?>
                        <button type="button" data-player-standard-key="<?php echo e($key); ?>"><?php echo $label; ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="player-use-standard" data-player-standard-use>Usar resultado como valor LP</button>
            </section>
            <section class="player-status-grid">
                <article><small>Tiempo</small><strong data-player-clock>Sin reloj</strong></article>
                <article><small>Turnos completados</small><strong data-player-turn-count>0</strong></article>
                <article class="player-dice-status"><small>Dados e inicio</small><strong data-player-dice>Esperando definición</strong></article>
            </section>
            <div class="player-toast" data-player-toast hidden></div>
        <?php endif; ?>
    </main>
    <?php if ($duelPlayerMatch): ?><script src="./assets/player-duel.js?v=20260927-surrender-calc-1" defer></script><?php endif; ?>
    <?php echo ygo_ui_labels_script($presentationLabels); ?>
    </body></html><?php
