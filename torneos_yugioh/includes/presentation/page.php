<?php require YGO_APP_ROOT . '/includes/presentation/layout/header.php'; ?>
<main class="app-main container-fluid px-lg-4 py-4">
    <?php if ($flashMessage): ?><div class="alert <?php echo e($flashClass); ?> mb-3 app-flash-message" data-flash-message><?php echo e($flashMessage); ?></div><?php endif; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/dashboard.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/crear_evento.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/buscar.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/clasificatorio.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/divisiones.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/calendario.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/resultados.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/deck.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/deck_ficha.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/equipos.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/equipo_ficha.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/estadisticas.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/torneos.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/copas.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/mundial.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/herramienta_lp.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/reglas.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/regla_ficha.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/reglamento.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/gestion.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/screens/evento_ficha.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/dialogs/events.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/dialogs/calendar-and-matches.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/dialogs/media.php'; ?>
<?php require YGO_APP_ROOT . '/includes/presentation/layout/footer.php'; ?>
