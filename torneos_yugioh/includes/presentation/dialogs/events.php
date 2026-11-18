<div class="modal fade dml-modal" id="deleteCompetitionModal" tabindex="-1" aria-hidden="true" data-delete-competition-modal>
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="action" value="delete_competition">
            <input type="hidden" name="competition_id" data-delete-competition-id>
            <div class="modal-header">
                <div>
                    <span class="tag danger">Eliminar evento</span>
                    <h2 class="modal-title">Eliminar evento completo</h2>
                    <p data-delete-competition-meta>Esta accion borra el evento y sus datos asociados de la base de datos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="note">Solo se pueden eliminar eventos que no esten activos, finalizados ni anulados. Para confirmar escribe <b>eliminar</b>.</p>
                <input class="form-control" name="delete_confirmation" placeholder="escribe eliminar" autocomplete="off" data-delete-competition-confirmation>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger fw-bold" type="submit" data-delete-competition-submit disabled><i class="bi bi-trash"></i> Eliminar definitivamente</button>
            </div>
        </form>
</div>
</div>

<div class="modal fade dml-modal" id="eventRankingModal" tabindex="-1" aria-hidden="true" data-event-ranking-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag" data-event-ranking-label>Tabla general</span>
                    <h2 class="modal-title" data-event-ranking-modal-title>Tabla del evento</h2>
                    <p data-event-ranking-modal-meta>Posiciones calculadas desde resultados persistidos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="ranking-phase-tabs" data-event-ranking-phase-tabs></div>
                <div class="event-ranking-list event-modal-list" data-competition-config-ranking></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="eventSavedReadingModal" tabindex="-1" aria-labelledby="event-saved-reading-title" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><div><span class="tag">Configuración del evento</span><h2 class="modal-title" id="event-saved-reading-title">Configuración guardada del evento</h2><p data-event-saved-reading-name></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
  <div class="modal-body"><p>Opciones guardadas del evento y su significado. Los cambios de la ficha aparecen aquí después de guardarlos.</p><div data-event-saved-reading style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.85rem"></div></div>
  <div class="modal-footer"><button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cerrar</button></div>
 </div></div>
</div>

<div class="modal fade dml-modal" id="eventRuleModal" tabindex="-1" aria-hidden="true" data-event-rule-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Regla aplicada</span>
                    <h2 class="modal-title" data-event-rule-modal-title>Regla del evento</h2>
                    <p data-event-rule-modal-meta>Validaciones que se usaran al cargar resultados de los duelos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="event-rule-detail" data-event-rule-modal-body></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="eventScheduleModal" tabindex="-1" aria-hidden="true" data-event-schedule-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Duelos programados</span>
                    <h2 class="modal-title" data-event-schedule-modal-title>Calendario del evento</h2>
                    <p data-event-schedule-modal-meta>Cruces y fechas tentativas guardadas en base de datos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="event-dependency-list event-modal-list" data-competition-config-pending-matches></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="confirmStartQualifierModal" tabindex="-1" aria-hidden="true" data-confirm-start-qualifier-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Confirmacion</span>
                    <h2 class="modal-title" data-confirm-start-title>Comenzar evento</h2>
                    <p data-confirm-start-subtitle>Al comenzar se bloqueara la configuracion del evento.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="note" data-confirm-start-message>Esta seguro de comenzar el evento?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary fw-bold" type="button" data-confirm-start-qualifier-submit><i class="bi bi-play-circle"></i> Si, comenzar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="confirmScheduleQualifierModal" tabindex="-1" aria-hidden="true" data-confirm-schedule-qualifier-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Programacion</span>
                    <h2 class="modal-title">Generar duelos y fechas</h2>
                    <p data-confirm-schedule-qualifier-meta>Se creara el calendario tentativo del clasificatorio.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="rule-note warn">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <p>
                        <strong>Confirmar programacion</strong><br>
                        <span data-confirm-schedule-qualifier-message>Si continuas, se generaran duelos y fechas tentativas en base de datos.</span>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning fw-bold" type="button" data-confirm-schedule-qualifier-submit><i class="bi bi-diagram-3"></i> Si, generar</button>
            </div>
        </div>
    </div>
</div>

