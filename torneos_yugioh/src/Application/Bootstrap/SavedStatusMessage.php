<?php
declare(strict_types=1);

namespace StationX\Yugioh\Application\Bootstrap;

final class SavedStatusMessage
{
    public static function resolve(
        string $savedStatus,
        ?string $seasonError,
        ?string $flashMessage,
        string $flashClass,
    ): array {
        $consumeSeasonError = false;
        if (in_array($savedStatus, ['temporada_error', 'evento_temporada_bloqueada'], true)) {
            $flashMessage = $seasonError ?? 'Revisa la temporada y las fechas del evento.';
            $consumeSeasonError = true;
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'temporada_guardada') {
            $flashMessage = 'Temporada actualizada correctamente.';
        }
        if ($savedStatus === 'resultado_bloqueado_fecha') {
            $flashMessage = 'Resultado bloqueado: el duelo no esta habilitado para ejecutarse en esta fecha segun la configuracion actual.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'resultado_bloqueado_inicio') {
            $flashMessage = 'Resultado bloqueado: primero debes lanzar los dados, definir quien comienza y confirmar Comenzar duelo.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'resultado_bloqueado_rendicion') {
            $flashMessage = 'Rendicion bloqueada: solo puede rendirse el lado que tiene el turno activo.';
            $flashClass = 'alert-warning';
        } elseif (in_array($savedStatus, ['calendario_bloqueado_regla', 'partido_bloqueado_regla'], true)) {
            $flashMessage = 'Calendario bloqueado: la fecha, hora o capacidad del dia no cumple las reglas configuradas.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'calendario_bloqueado_finalizado') {
            $flashMessage = 'No se cambio la fecha: un duelo finalizado conserva su programacion historica.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'calendario_reprogramado') {
            $flashMessage = 'Duelo reprogramado correctamente. El calendario del evento fue actualizado.';
            $flashClass = 'alert-success';
        } elseif ($savedStatus === 'partido_bloqueado_pool') {
            $flashMessage = 'Partido bloqueado: uno o mas decks no pertenecen al pool habilitado para ese evento.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'partido_invalido') {
            $flashMessage = 'No se creo el amistoso: selecciona dos decks diferentes y vuelve a intentarlo.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'partido_manual') {
            $flashMessage = 'Combate amistoso creado y agregado al calendario.';
            $flashClass = 'alert-success';
        } elseif ($savedStatus === 'amistoso_eliminado') {
            $flashMessage = 'Combate amistoso eliminado junto con sus datos asociados.';
            $flashClass = 'alert-success';
        } elseif ($savedStatus === 'amistoso_no_eliminado') {
            $flashMessage = 'No se elimino el combate: solo pueden borrarse amistosos independientes creados manualmente.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'deck_bloqueado_evento') {
            $flashMessage = 'Deck bloqueado: no se puede eliminar porque esta asociado a un evento activo, pendiente, pendiente de evento o finalizado.';
            $flashClass = 'alert-warning';
        } elseif ($savedStatus === 'division_minimo_4_decks') {
            $flashMessage = 'Division bloqueada: selecciona minimo 4 decks para crear o guardar una division.';
            $flashClass = 'alert-warning';
        } elseif (preg_match('/^evento_avanzado_(\d+)_(\d+)$/', $savedStatus, $match)) {
            $created = (int) $match[2];
            $flashMessage = $created > 0
                ? 'Siguiente fase creada: ' . $created . ' combates nuevos generados.'
                : 'No se creo una nueva fase: verifica que la fase anterior este finalizada y tenga ganadores.';
            $flashClass = $created > 0 ? 'alert-success' : 'alert-warning';
        } elseif (preg_match('/^divisiones_aplicadas_(\d+)_(\d+)$/', $savedStatus, $match)) {
            $moved = (int) $match[2];
            $flashMessage = $moved > 0
                ? 'Divisiones aplicadas desde clasificatorio: ' . $moved . ' decks asignados.'
                : 'No se aplicaron divisiones: falta finalizar el clasificatorio o configurar rangos validos.';
            $flashClass = $moved > 0 ? 'alert-success' : 'alert-warning';
        } elseif (preg_match('/^movimientos_liga_(\d+)_(\d+)$/', $savedStatus, $match)) {
            $moved = (int) $match[2];
            $flashMessage = $moved > 0
                ? 'Ascensos/descensos aplicados: ' . $moved . ' movimientos registrados.'
                : 'No se aplicaron movimientos: la liga no esta cerrada, no hay cupos configurados o los movimientos estan bloqueados.';
            $flashClass = $moved > 0 ? 'alert-success' : 'alert-warning';
        } elseif (preg_match('/^ranking_recalculado_(\d+)_(\d+)$/', $savedStatus, $match)) {
            $ok = (int) $match[2];
            $flashMessage = $ok > 0
                ? 'Ranking recalculado: snapshots general y por fase actualizados.'
                : 'No se recalculo ranking: el evento no existe o no esta activo.';
            $flashClass = $ok > 0 ? 'alert-success' : 'alert-warning';
        } elseif (preg_match('/^qa_codex_archivado_(\d+)$/', $savedStatus, $match)) {
            $count = (int) $match[1];
            $flashMessage = $count > 0
                ? 'Datos QA Codex archivados: ' . $count . ' registros marcados como inactivos.'
                : 'No habia datos QA Codex activos para archivar.';
            $flashClass = $count > 0 ? 'alert-success' : 'alert-warning';
        } elseif ($savedStatus !== '') {
            $flashMessage = 'Cambio guardado: ' . $savedStatus;
            $flashClass = 'alert-success';
        }
        return [
            'message' => $flashMessage,
            'class' => $flashClass,
            'consumeSeasonError' => $consumeSeasonError,
        ];
    }
}
