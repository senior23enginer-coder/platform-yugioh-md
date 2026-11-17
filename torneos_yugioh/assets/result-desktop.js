(function () {
  'use strict';
  const root = document.querySelector('#resultCaptureModal'); if (!root) return;
  const desktop = matchMedia('(min-width: 1100px)');
  function sync() {
    root.querySelectorAll('.result-history-sheet,.result-outcome-sheet').forEach(sheet => {
      sheet.setAttribute('aria-hidden', desktop.matches ? 'false' : String(!sheet.classList.contains('is-open')));
      const panel = sheet.querySelector('[aria-modal]');
      if (panel) { panel.setAttribute('role', desktop.matches ? 'group' : 'dialog'); panel.setAttribute('aria-modal', desktop.matches ? 'false' : 'true'); }
    });
  }
  // Three.js measures the stage when the modal may still be hidden. Resize
  // again after layout and whenever its actual dimensions change.
  const stage = root.querySelector('[data-result-dice-stage]');
  let previousSize = '';
  let resizeFrame;
  function resizeDice() {
    if (!stage || !root.classList.contains('show')) return;
    const rect = stage.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    const size = Math.round(rect.width) + ':' + Math.round(rect.height);
    if (size === previousSize) return;
    previousSize = size;
    cancelAnimationFrame(resizeFrame);
    resizeFrame = requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
  }
  if (stage && window.ResizeObserver) new ResizeObserver(resizeDice).observe(stage);
  desktop.addEventListener('change', sync);
  root.addEventListener('click', event => {
    const button = event.target.closest('[data-result-outcome-open]');
    if (!desktop.matches || !button || button.disabled) return;
    const sheet = root.querySelector(`[data-result-outcome-sheet="${button.dataset.resultOutcomeOpen}"]`);
    const select = sheet?.querySelector('select');
    if (select && !select.disabled) { sheet.scrollIntoView({block:'nearest'}); select.focus({preventScroll:true}); }
  });
  root.addEventListener('shown.bs.modal', () => { sync(); previousSize = ''; resizeDice(); });
  sync();
})();
