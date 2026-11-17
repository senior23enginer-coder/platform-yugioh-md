<div class="modal fade dml-modal" id="eventAuditModal" tabindex="-1" aria-labelledby="event-audit-title" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><div><span class="tag">Historial de la ficha</span><h2 class="modal-title" id="event-audit-title">Auditoría del evento</h2><p data-event-audit-name></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
  <div class="modal-body">
   <p>Acciones y cambios guardados de este evento, del más reciente al más antiguo.</p>
   <form class="d-flex gap-2 mb-3" data-event-audit-search-form><label class="flex-grow-1">Buscar en el historial<input type="search" class="form-control" maxlength="150" placeholder="Acción, fecha o detalle" data-event-audit-search></label><button type="submit" class="btn btn-outline-info align-self-end">Buscar</button></form>
   <div role="status" aria-live="polite" data-event-audit-message></div><div data-event-audit-rows></div>
   <button type="button" class="btn btn-outline-info mt-3" data-event-audit-more hidden>Cargar anteriores</button>
  </div><div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cerrar</button></div>
 </div></div>
</div>
