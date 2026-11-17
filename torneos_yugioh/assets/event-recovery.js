(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.createElement('div');
    modal.className = 'modal fade dml-modal'; modal.tabIndex = -1; modal.dataset.recoveryModal = '';
    modal.innerHTML = `<div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" method="post">
      <div class="modal-header"><h2>Corregir fase anterior</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body"><input type="hidden" name="action" value="revert_event_stage"><input type="hidden" name="competition_id"><input type="hidden" name="expected_round"><input type="hidden" name="recovery_token">
        <p data-recovery-summary></p><p>Se retiraran solo los cruces de la ultima fase si todavia no tienen resultados, turnos ni datos capturados. Los resultados anteriores se conservan y podras corregirlos antes de crear otra vez la siguiente fase.</p>
        <label class="d-block">Motivo de la correccion<textarea name="recovery_reason" class="form-control bg-dark text-light" required minlength="10" maxlength="500" rows="3"></textarea></label>
        <p class="note">Un evento cerrado o una fase utilizada por otro evento no se puede revertir desde aqui.</p>
      </div><div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-warning" type="submit" data-recovery-submit>Revertir ultima fase pendiente</button></div>
    </form></div>`;
    document.body.append(modal);
    document.querySelectorAll('[data-open-event-recovery]').forEach(button => button.addEventListener('click', () => {
      const id = button.closest('form')?.querySelector('[name="competition_id"]')?.value || new URL(location.href).searchParams.get('event_id');
      const c = (window.DML_DATA?.competitions || []).find(row => String(row.id) === String(id));
      const s = c?.workflow;
      const allowed = !!(s && !s.complete && s.pending?.length && s.history?.length && s.round > 1 && !['completed', 'closed', 'cancelled', 'archived'].includes(c.status));
      modal.querySelector('[name="competition_id"]').value = c?.id || '';
      modal.querySelector('[name="expected_round"]').value = s?.round || '';
      modal.querySelector('[name="recovery_token"]').value = c?.recoveryToken || '';
      modal.querySelector('[name="recovery_reason"]').value = '';
      modal.querySelector('[data-recovery-summary]').textContent = allowed
        ? `${c.name}: se retirara la ronda ${s.round} (${s.pending.length} combates) para corregir la ronda ${s.round - 1}.`
        : 'Este evento no tiene una fase pendiente posterior a resultados consumidos. No hay una fase que se pueda revertir.';
      modal.querySelector('[data-recovery-submit]').disabled = !allowed;
      bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
    modal.querySelector('form').addEventListener('submit', () => { modal.querySelector('[data-recovery-submit]').disabled = true; });
  });
})();
